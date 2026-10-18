<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Rtp\MediaChannel;
use libspech\Sip\trunkController;
use Swoole\Coroutine;

function lifecycleCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

final class LifecycleUdpSocket extends SocketMutable
{
    public mixed $afterAudio = null;
    public int $audioCount = 0;
    public array $sent = [];

    public function sendto(string $addr, int $port, string $data): int|false
    {
        $this->sent[] = [hrtime(true), $data];
        $sent = parent::sendto($addr, $port, $data);
        lifecycleCheck($sent === strlen($data), 'sendto failed');
        if ((ord($data[1]) & 0x7f) !== 101) {
            ($this->afterAudio)(++$this->audioCount);
        }
        return $sent;
    }
}

function lifecycleCodec(string $wav, string $codec): void
{
    $initial = Coroutine::stats()['coroutine_num'];
    $sender = new LifecycleUdpSocket(AF_INET, SOCK_DGRAM, SOL_UDP);
    $receiver = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $receiver->bind('127.0.0.1', 0);
    $port = $receiver->getsockname()['port'];
    $media = new MediaChannel($sender, 'udp-lifecycle');
    $trunk = (new ReflectionClass(trunkController::class))->newInstanceWithoutConstructor();
    $trunk->codecName = $codec;
    $frequency = $codec === 'OPUS' ? 48000 : 8000;
    $trunk->frequencyCall = $frequency;
    $trunk->audioRemoteIp = '127.0.0.1';
    $trunk->audioRemotePort = $port;
    $trunk->callActive = true;
    $trunk->mediaChannel = $media;
    $pt = match ($codec) { 'PCMA' => 8, 'PCMU' => 0, 'G729' => 18, 'GSM' => 3, 'OPUS' => 111 };
    $media->addMember(['address' => '127.0.0.1', 'port' => $port, 'codec' => $codec, 'pt' => $pt,
        'frequency' => $frequency, 'channels' => 1, 'config' => []]);
    $rtp = $media->members['127.0.0.1:' . $port]['rtpChannel'];
    $rtp->sequenceNumber = 65530; // Exercise wrap through DTMF.
    $rtp->timestamp = 0xfffff000;
    $trunk->defineAudioFile($wav);
    $sender->afterAudio = static function (int $number) use ($trunk, $media, $wav): void {
        if ($number === 4) {
            $trunk->setPacketTime(40);
        } elseif ($number === 7) {
            Coroutine::create(static fn() => $media->send2833('5'));
        } elseif ($number === 10) {
            // Replace a source while its producer is blocked on a full queue.
            $trunk->defineAudioFile($wav);
        } elseif ($number === 15) {
            $media->active = false;
        }
    };
    $received = [];
    $collect = true;
    $done = new Swoole\Coroutine\Channel(1);
    Coroutine::create(static function () use ($receiver, &$received, &$collect, $done): void {
        while ($collect) {
            $peer = [];
            $packet = $receiver->recvfrom($peer, .03);
            if ($packet !== false) {
                $received[] = $packet;
            }
        }
        $done->push(true);
    });
    (new ReflectionMethod($trunk, 'runAudioTransmissionLoop'))->invoke($trunk);
    Coroutine::sleep(.005);
    $collect = false;
    lifecycleCheck($done->pop(1) === true, 'Receiver did not exit');
    lifecycleCheck(count($received) === count($sender->sent), 'UDP loss');
    $previous = null;
    $lastAudio = null;
    $eventTs = null;
    $audioCount = 0;
    foreach ($sender->sent as $index => [$time, $packet]) {
        lifecycleCheck($received[$index] === $packet, 'Received packet differs');
        $h = unpack('Cx/Cpt/nseq/Nts/Nssrc', $packet);
        if ($previous !== null) {
            lifecycleCheck((($h['seq'] - $previous['seq']) & 0xffff) === 1, 'Sequence discontinuity');
            lifecycleCheck($h['ssrc'] === $previous['ssrc'], 'SSRC changed');
        }
        $previous = $h;
        if (($h['pt'] & 0x7f) === 101) {
            $eventTs ??= $h['ts'];
            lifecycleCheck($h['ts'] === $eventTs, 'DTMF timestamp changed');
            continue;
        }
        lifecycleCheck(($h['pt'] & 0x7f) === $pt, 'Wrong audio PT');
        $audioCount++;
        if ($lastAudio !== null) {
            $expected = (int)round($frequency * ($audioCount <= 5 ? .020 : .040));
            if ($audioCount === 8) {
                $expected += (int)round($frequency * .160);
            }
            lifecycleCheck((($h['ts'] - $lastAudio['ts']) & 0xffffffff) === $expected, "$codec audio timeline discontinuity #$audioCount");
            $interval = ($time - $lastAudio['time']) / 1e6;
            lifecycleCheck($interval >= ($audioCount <= 4 ? 15 : 30), "$codec audio burst #$audioCount: $interval ms");
        }
        $lastAudio = $h + ['time' => $time];
    }
    lifecycleCheck($audioCount === 15 && $eventTs !== null, 'Audio or DTMF missing');
    $trunk->stopAudioFile();
    $sender->destroy();
    $receiver->destroy();
    $media->eventSock->destroy();
    Coroutine::sleep(.005);
    lifecycleCheck(Coroutine::stats()['coroutine_num'] === $initial, 'Pending producer/sender/receiver');
    echo "$codec: real UDP, dynamic ptime, DTMF pause/resume, RTP wrap, replacement and shutdown OK\n";
}

$wav = tempnam(sys_get_temp_dir(), 'libspech-lifecycle-');
extra_write_wav_pcm16le($wav, str_repeat("\x10\x30", 8000), 8000, 1);
try {
    Swoole\Coroutine\run(static function () use ($wav): void {
        foreach (['PCMA' => null, 'PCMU' => null, 'G729' => 'bcg729Channel', 'GSM' => 'gsmChannel', 'OPUS' => 'opusChannel'] as $codec => $class) {
            if ($class === null || class_exists($class)) {
                lifecycleCodec($wav, $codec);
            } else {
                echo "SKIP $codec: extension unavailable\n";
            }
        }
    });
} finally {
    unlink($wav);
}
