<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpChannel;
use libspech\Sip\trunkController;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

function receiveCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function receiveRegression(string $codec, bool $receiveOnly, bool $dynamicMappings = false): void
{
    $done = new Channel(1);
    $receiver = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $sender = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $target = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    foreach ([$receiver, $sender, $target] as $socket) {
        $socket->bind('127.0.0.1', 0);
    }
    $sourcePort = $sender->getsockname()['port'];
    $media = new MediaChannel($receiver, 'receive-only-validation');
    receiveCheck(!(new ReflectionProperty($media, 'receiveOnlyMode'))->getValue($media), 'Default mode changed');
    $trunk = (new ReflectionClass(trunkController::class))->newInstanceWithoutConstructor();
    $trunk->socketInUse = false;
    $trunk->ssrc = 123456;
    $trunk->codecName = $codec;
    $rate = $codec === 'OPUS' ? 48000 : 8000;
    $trunk->frequencyCall = $rate;
    $trunk->audioRemoteIp = '127.0.0.1';
    $trunk->audioRemotePort = $sourcePort;
    $trunk->audioReceivePort = $receiver->getsockname()['port'];
    $trunk->audioRecordingEnabled = true;
    $trunk->enableVAD();
    $trunk->mediaChannel = $media;
    $pt = match ($codec) { 'PCMU' => 0, 'PCMA' => 8, 'G729' => 18, 'GSM' => 3, 'OPUS' => 111 };
    $pt = $dynamicMappings ? 110 : $pt;
    $rxPt = $dynamicMappings ? 96 : $pt;
    (new ReflectionProperty($trunk, 'ptUse'))->setValue($trunk, $pt);
    (new ReflectionProperty($trunk, 'txPt'))->setValue($trunk, $pt);
    (new ReflectionProperty($trunk, 'rxPt'))->setValue($trunk, $rxPt);
    $trunk->txCodecMapper = [$pt => "$codec/$rate/1", 101 => 'telephone-event/8000/1'];
    $trunk->rxCodecMapper = [$rxPt => "$codec/$rate/1", 101 => 'telephone-event/8000/1'];
    $pcmCount = 0;
    $pcmBytes = 0;
    $allPcm = '';
    $voiceSeen = $silenceSeen = false;
    $trunk->onReceivePcm(static function (string $pcm, array $peer, trunkController $phone, string $receivedCodec, int $frequency) use ($codec, $rate, &$pcmCount, &$pcmBytes, &$allPcm, &$voiceSeen, &$silenceSeen): void {
        receiveCheck($receivedCodec === $codec && $frequency === $rate, 'Callback codec/clock mismatch');
        receiveCheck(strlen($pcm) === (int)($rate * .020) * 2, "$codec decoded PCM frame length");
        $pcmCount++;
        $pcmBytes += strlen($pcm);
        $allPcm .= $pcm;
        $voiceSeen = $voiceSeen || $phone->isVoiceActive;
        $silenceSeen = $silenceSeen || ($voiceSeen && !$phone->isVoiceActive);
    });
    $dtmf = [];
    $trunk->onDtmfCallable = static function ($digit) use (&$dtmf): void { $dtmf[] = $digit; };
    $trunk->receiveMedia();
    receiveCheck((new ReflectionProperty($media, 'receiveOnlyMode'))->getValue($media), 'Trunk did not enable receive-only');
    $media->setReceiveOnlyMode($receiveOnly);
    $targetPort = $target->getsockname()['port'];
    $media->addMember(['address' => '127.0.0.1', 'port' => $targetPort, 'codec' => $codec,
        'pt' => $pt, 'frequency' => $rate, 'channels' => 1, 'config' => []]);
    $targetPackets = [];
    $collect = true;
    Coroutine::create(static function () use ($target, &$collect, &$targetPackets, $done): void {
        while ($collect) {
            $peer = [];
            $packet = $target->recvfrom($peer, .05);
            if ($packet !== false) {
                $targetPackets[] = $packet;
            }
        }
        $target->destroy();
        $done->push(true);
    });
    $rtp = new rtpChannel($rxPt, $rate, 20, 998877);
    $encoder = match ($codec) {
        'G729' => new bcg729Channel(), 'GSM' => new gsmChannel(), 'OPUS' => new opusChannel(48000, 1), default => null,
    };
    $silenceResult = null;
    for ($frame = 0; $frame < 54; $frame++) {
        if ($frame === 6) {
            Coroutine::create(static function () use ($trunk, &$silenceResult): void {
                $silenceResult = $trunk->waitSilence(true, .04);
            });
        }
        $pcm = '';
        for ($sample = 0; $sample < (int)($rate * .020); $sample++) {
            $value = $frame < 6 ? (int)(sin(2 * M_PI * 437 * ($frame * $rate * .020 + $sample) / $rate) * 12000) : 0;
            $pcm .= pack('v', $value & 0xffff);
        }
        $payload = match ($codec) { 'PCMA' => encodePcmToPcma($pcm), 'PCMU' => encodePcmToPcmu($pcm), default => $encoder->encode($pcm) };
        $sender->sendto('127.0.0.1', $receiver->getsockname()['port'], $rtp->buildAudioPacket($payload));
        Coroutine::sleep(.006);
    }
    for ($repeat = 0; $repeat < 3; $repeat++) {
        $packet = $rtp->buildDtmfForwardPacket(pack('CCn', 5, 0x8a, 160), 990000, $repeat === 0);
        $sender->sendto('127.0.0.1', $receiver->getsockname()['port'], $packet);
        Coroutine::sleep(.006);
    }
    if ($receiveOnly) {
        // Exercise the idle branch as well: a receive-only trunk cannot inject
        // a silence probe to either member after the receive timeout.
        Coroutine::sleep(1.05);
    }
    receiveCheck($pcmCount === 54, "$codec: missing PCM callbacks ($pcmCount/54)");
    $recording = $trunk->getBufferWriteSoundBySsrc(998877);
    receiveCheck(strlen($recording[$rate][1] ?? '') === $pcmBytes, "$codec recording mismatch");
    receiveCheck(($recording[$rate][1] ?? '') === $allPcm, "$codec recording differs from PCM callback");
    receiveCheck($voiceSeen && $silenceSeen, "$codec VAD did not see voice and silence");
    receiveCheck($silenceResult === true, "$codec waitSilence did not complete");
    receiveCheck(count($dtmf) === 1, "$codec DTMF callback missing or duplicated");
    $audioPackets = array_filter($targetPackets, static fn($p) => (ord($p[1]) & 0x7f) === $pt);
    receiveCheck($receiveOnly ? count($targetPackets) === 0 : count($audioPackets) === 54,
        "$codec relay mismatch: " . count($targetPackets));
    receiveCheck(count($media->rtpChans) === 1, 'Source RTP registration lost');
    receiveCheck(isset($media->members['127.0.0.1:' . $sourcePort]), 'Source member registration lost');
    $media->close();
    $collect = false;
    receiveCheck($done->pop(2) === true, 'Receiver coroutine did not exit');
    $sender->destroy();
    Coroutine::sleep(.01);
    receiveCheck($receiver->isClosed() && $media->eventSock->isClosed() && $target->isClosed(), 'Sockets still open');
    receiveCheck($media->members === [] && $media->rtpChans === [], 'Member/RTP buffers retained after close');
    echo "$codec receiveOnly=" . (int)$receiveOnly . " rxPt=$rxPt txPt=$pt: PCM, recording, VAD, silence, DTMF, relay and teardown OK\n";
}

Swoole\Coroutine\run(static function (): void {
    $initial = Coroutine::stats()['coroutine_num'];
    foreach (['PCMA' => null, 'PCMU' => null, 'G729' => 'bcg729Channel', 'GSM' => 'gsmChannel', 'OPUS' => 'opusChannel'] as $codec => $class) {
        if ($class !== null && !class_exists($class)) {
            echo "SKIP $codec: extension unavailable\n";
            continue;
        }
        foreach ([false, true] as $mode) {
            receiveRegression($codec, $mode);
        }
        if ($codec === 'G729') {
            receiveRegression($codec, false, true);
            receiveRegression($codec, true, true);
        }
    }
    receiveCheck(Coroutine::stats()['coroutine_num'] === $initial, 'Pending media coroutines');
});
