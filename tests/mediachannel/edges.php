<?php

declare(strict_types=1);

$root = realpath($argv[1] ?? __DIR__ . '/../..');
chdir($root);
require $root . '/plugins/autoloader.php';

use libspech\Rtp\{MediaChannel, rtpc};
use Swoole\Coroutine as Co;

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function udp(): SocketMutable
{
    $socket = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    check($socket->bind('127.0.0.1', 0), 'bind');
    return $socket;
}

final class CountingSocket extends SocketMutable
{
    public int $reads = 0;
    public function recvfrom(mixed &$peername, float $timeout = 0): string|false
    {
        $this->reads++;
        return parent::recvfrom($peername, $timeout);
    }
}

Co\run(function (): void {
    $header = pack('CCnNN', 128, 8, 1, 0, 42);
    $audio = str_repeat("\xd5", 160);
    $cases = [
        'short' => ['x', false],
        'version' => ["\x40" . substr($header, 1) . $audio, false],
        'csrc truncated' => ["\x81" . substr($header, 1), false],
        'extension truncated' => ["\x90" . substr($header, 1) . "\x00\x00\x00", false],
        'extension overflow' => ["\x90" . substr($header, 1) . pack('nn', 0, 100), false],
        'padding empty' => ["\xa0" . substr($header, 1), false],
        'padding zero' => ["\xa0" . substr($header, 1) . "\x00", false],
        'padding overflow' => ["\xa0" . substr($header, 1) . "\x02", false],
        'empty RTP keepalive' => [$header, true],
        'plain' => [$header . $audio, true],
        'csrc' => ["\x81" . substr($header, 1) . pack('N', 9) . $audio, true],
        'extension' => ["\x90" . substr($header, 1) . pack('nnN', 0xbede, 1, 0) . $audio, true],
        'padding' => ["\xa0" . substr($header, 1) . $audio . "\x00\x02", true],
    ];
    foreach ([false, true] as $native) {
        $socket = udp();
        $sender = udp();
        $media = new MediaChannel($socket, 'validation');
        $media->setNativeRtpReceive($native);
        $media->setReceiveOnlyMode(true);
        $received = [];
        $media->onReceive(function ($packet) use (&$received): void {
            $received[] = $packet->payloadRaw;
        });
        $media->start();
        foreach ($cases as $label => [$bytes, $valid]) {
            $before = count($received);
            $sender->sendto('127.0.0.1', $socket->getsockname()['port'], $bytes);
            Co::sleep(.003);
            check(count($received) - $before === (int)$valid, "$label native=$native");
            if ($label === 'padding') {
                check(end($received) === $audio, 'padding removed before callback');
            }
        }
        $media->close();
        Co::sleep(.005);
        check($socket->isClosed(), 'cancel closes owner socket');
        $sender->close();
    }
    foreach (['PCMA' => 8, 'PCMU' => 0] as $codec => $pt) {
        foreach ([10, 20, 30, 40] as $ptime) {
            $socket = udp();
            $sender = udp();
            $destination = udp();
            $media = new MediaChannel($socket, 'partial');
            $media->setSettings(['sendSilenceProbeToMembers' => false]);
            foreach ([$sender, $destination] as $peer) {
                $media->addMember([
                    'address' => '127.0.0.1', 'port' => $peer->getsockname()['port'],
                    'codec' => $codec, 'pt' => $pt, 'frequency' => 8000, 'ptime' => $ptime,
                ]);
            }
            $samples = $ptime * 8;
            $original = str_repeat(implode('', array_map(chr(...), range(0, 255))), 4);
            $original = substr($original, 0, 3 * $samples);
            $media->start();
            // A matching actual duration is forwarded byte for byte even when
            // the declared source ptime differs. PCMU negative zero detects a recode.
            $sourceId = '127.0.0.1:' . $sender->getsockname()['port'];
            $media->members[$sourceId]['rtpChannel']->setPacketTime($ptime * 2);
            $direct = str_repeat("\x7f", $samples);
            $sender->sendto(
                '127.0.0.1',
                $socket->getsockname()['port'],
                pack('CCnNN', 128, $pt, 99, 0, 42) . $direct
            );
            $directPeer = [];
            $directPacket = $destination->recvfrom($directPeer, .5);
            check(is_string($directPacket) && substr($directPacket, 12) === $direct, 'encoded direct relay');
            // Residual + exactly one frame + a larger chunk must stay in order.
            $chunks = [substr($original, 0, 17), substr($original, 17, $samples), substr($original, 17 + $samples)];
            foreach ($chunks as $seq => $chunk) {
                $sender->sendto(
                    '127.0.0.1',
                    $socket->getsockname()['port'],
                    pack('CCnNN', 128, $pt, $seq, 0, 42) . $chunk
                );
            }
            $output = '';
            $previous = null;
            $peer = [];
            for ($i = 0; $i < 3; $i++) {
                $bytes = $destination->recvfrom($peer, .5);
                check(is_string($bytes), "$codec/$ptime output $i");
                $packet = new rtpc($bytes);
                check(strlen($packet->payloadRaw) === $samples, 'frame size');
                if ($previous) {
                    check((($packet->sequence - $previous->sequence) & 65535) === 1, 'sequence');
                    check((($packet->timestamp - $previous->timestamp) & 0xffffffff) === $samples, 'duration');
                }
                $output .= $packet->payloadRaw;
                $previous = $packet;
            }
            $decode = $codec === 'PCMA' ? decodePcmaToPcm(...) : decodePcmuToPcm(...);
            check($decode($output) === $decode($original), 'all decoded samples preserved');
            $media->close();
            Co::sleep(.005);
            $sender->close();
            $destination->close();
        }
    }
    $socket = new CountingSocket(AF_INET, SOCK_DGRAM, SOL_UDP);
    $socket->bind('127.0.0.1', 0);
    $media = new MediaChannel($socket, 'idle');
    $media->setNativeRtpReceive(false);
    $media->connectTimeout = 1;
    $timeouts = 0;
    $media->packetOnTimeout(function () use (&$timeouts): void {
        $timeouts++;
    });
    $media->start();
    Co::sleep(.6);
    check($socket->reads === 1, 'idle read has no periodic polling');
    Co::sleep(.5);
    check($timeouts === 1 && !$media->active, 'deadline timeout');
    $media->close();
    echo "edges: PASS\n";
});
