<?php

declare(strict_types=1);

ini_set('memory_limit', '256M');
require 'plugins/autoloader.php';

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpChannel;

final class MemberPtimeCaptureSocket extends SocketMutable
{
    /** @var list<array{address:string,port:int,data:string}> */
    public array $packets = [];

    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public function isClosed(): bool
    {
        return false;
    }

    public function sendto(string $addr, int $port, string $data): int|false
    {
        $this->packets[] = ['address' => $addr, 'port' => $port, 'data' => $data];
        return strlen($data);
    }
}

function memberPtimeAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nEsperado: %s\nObtido:  %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function memberPtimeMedia(MemberPtimeCaptureSocket $socket): MediaChannel
{
    $reflection = new ReflectionClass(MediaChannel::class);
    /** @var MediaChannel $media */
    $media = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('onDestructCallable')->setValue($media, static function (): void {
    });
    $media->socket = $socket;
    return $media;
}

/** @return array{sequence:int,timestamp:int,payload:string} */
function memberPtimeDecodeRtp(string $packet): array
{
    $header = unpack('Cfirst/Csecond/nsequence/Ntimestamp/Nssrc', substr($packet, 0, 12));
    return [
        'sequence' => $header['sequence'],
        'timestamp' => $header['timestamp'],
        'payload' => substr($packet, 12),
    ];
}

function memberPtimePcm(int $samples, int $firstSample = 0): string
{
    $pcm = '';
    for ($sample = 0; $sample < $samples; $sample++) {
        $value = (($firstSample + $sample) * 173) % 50000 - 25000;
        $pcm .= pack('v', $value & 0xffff);
    }
    return $pcm;
}

function memberPtimeAdd(
    MediaChannel $media,
    string $leg,
    int $port,
    string $codec,
    int $ptime,
    int $frequency = 8000,
    int $channels = 1
): string {
    $payloadType = match ($codec) {
        'PCMU' => rtpChannel::PAYLOAD_PCMU,
        'PCMA' => rtpChannel::PAYLOAD_PCMA,
        'G729' => rtpChannel::PAYLOAD_G729,
        'OPUS' => 111,
        'L16' => 96,
        default => 97,
    };
    $media->addMember([
        'address' => '127.0.0.1',
        'port' => $port,
        'leg' => $leg,
        'codec' => $codec,
        'pt' => $payloadType,
        'frequency' => $frequency,
        'channels' => $channels,
        'ptime' => $ptime,
        'config' => [],
    ]);
    return '127.0.0.1:' . $port;
}

function memberPtimeTestRepacketization(string $codec, int $sourcePtime, int $targetPtime, int $inputFrames): void
{
    $socket = new MemberPtimeCaptureSocket();
    $media = memberPtimeMedia($socket);
    $id = memberPtimeAdd($media, 'b', 21000 + $sourcePtime + $targetPtime, $codec, $targetPtime);
    $channel = $media->members[$id]['rtpChannel'];
    $channel->sequenceNumber = 32000;
    $channel->timestamp = 900000;

    $sourceSamples = intdiv(8000 * $sourcePtime, 1000);
    $targetSamples = intdiv(8000 * $targetPtime, 1000);
    $allPcm = memberPtimePcm($sourceSamples * $inputFrames);

    for ($frame = 0; $frame < $inputFrames; $frame++) {
        $pcm = substr($allPcm, $frame * $sourceSamples * 2, $sourceSamples * 2);
        $media->sendPcmToLeg('b', $pcm, 8000, 1);
    }

    $expectedPackets = intdiv($sourceSamples * $inputFrames, $targetSamples);
    $expectedResidualSamples = ($sourceSamples * $inputFrames) % $targetSamples;
    $label = "$codec $sourcePtime->$targetPtime";
    memberPtimeAssertSame($expectedPackets, count($socket->packets), "$label: quantidade de pacotes");

    for ($packetIndex = 0; $packetIndex < $expectedPackets; $packetIndex++) {
        $decoded = memberPtimeDecodeRtp($socket->packets[$packetIndex]['data']);
        $expectedPcm = substr($allPcm, $packetIndex * $targetSamples * 2, $targetSamples * 2);
        $expectedPayload = $codec === 'PCMA' ? encodePcmToPcma($expectedPcm) : encodePcmToPcmu($expectedPcm);
        memberPtimeAssertSame($targetSamples, strlen($decoded['payload']), "$label: tamanho do payload #$packetIndex");
        memberPtimeAssertSame($expectedPayload, $decoded['payload'], "$label: PCM sem perda/duplicação #$packetIndex");
        memberPtimeAssertSame((32000 + $packetIndex) & 0xffff, $decoded['sequence'], "$label: sequence #$packetIndex");
        memberPtimeAssertSame(900000 + ($packetIndex * $targetSamples), $decoded['timestamp'], "$label: timestamp #$packetIndex");
    }

    $expectedResidual = substr($allPcm, $expectedPackets * $targetSamples * 2);
    memberPtimeAssertSame($expectedResidualSamples * 2, strlen($media->members[$id]['pcmAccumulator']), "$label: tamanho residual");
    memberPtimeAssertSame($expectedResidual, $media->members[$id]['pcmAccumulator'], "$label: conteúdo residual");
    memberPtimeAssertSame($targetPtime, $media->members[$id]['ptime'], "$label: ptime do membro");
    memberPtimeAssertSame($targetSamples, $media->members[$id]['samplesPerPacket'], "$label: samples por pacote");
    memberPtimeAssertSame(32000 + $expectedPackets, $channel->sequenceNumber, "$label: sequence final do destino");
    memberPtimeAssertSame(900000 + ($expectedPackets * $targetSamples), $channel->timestamp, "$label: timestamp final do destino");
}

$cases = [
    [10, 10, 3],
    [20, 20, 3],
    [10, 20, 3], // Caso explícito: 1+2 envia; 3 permanece residual.
    [20, 10, 3],
    [10, 40, 5],
    [40, 10, 3],
];
foreach (['PCMA', 'PCMU'] as $codec) {
    foreach ($cases as [$sourcePtime, $targetPtime, $inputFrames]) {
        memberPtimeTestRepacketization($codec, $sourcePtime, $targetPtime, $inputFrames);
    }
}

// O default continua em 20 ms; somente membros sem ptime explícito acompanham o setter global.
$defaultSocket = new MemberPtimeCaptureSocket();
$defaultMedia = memberPtimeMedia($defaultSocket);
$defaultMedia->addMember([
    'address' => '127.0.0.1',
    'port' => 22001,
    'leg' => 'a',
    'codec' => 'PCMA',
    'pt' => rtpChannel::PAYLOAD_PCMA,
    'frequency' => 8000,
    'config' => [],
]);
$explicitId = memberPtimeAdd($defaultMedia, 'b', 22002, 'PCMU', 10);
$defaultId = '127.0.0.1:22001';
memberPtimeAssertSame(20, $defaultMedia->members[$defaultId]['ptime'], 'default legado do membro');
memberPtimeAssertSame(160, $defaultMedia->members[$defaultId]['samplesPerPacket'], 'samples do default legado');
$defaultMedia->setPacketTime(40);
memberPtimeAssertSame(40, $defaultMedia->members[$defaultId]['ptime'], 'membro herdado acompanha default global');
memberPtimeAssertSame(10, $defaultMedia->members[$explicitId]['ptime'], 'membro explícito preserva ptime individual');

// Accumulators de dois destinos não interferem entre si.
$isolationSocket = new MemberPtimeCaptureSocket();
$isolationMedia = memberPtimeMedia($isolationSocket);
$id20 = memberPtimeAdd($isolationMedia, 'a', 23020, 'PCMA', 20);
$id40 = memberPtimeAdd($isolationMedia, 'b', 23040, 'PCMU', 40);
$pcm30ms = memberPtimePcm(240);
for ($frame = 0; $frame < 3; $frame++) {
    $pcm10ms = substr($pcm30ms, $frame * 160, 160);
    $isolationMedia->sendPcmToLeg('a', $pcm10ms, 8000);
    $isolationMedia->sendPcmToLeg('b', $pcm10ms, 8000);
}
memberPtimeAssertSame(1, count(array_filter($isolationSocket->packets, static fn(array $packet): bool => $packet['port'] === 23020)), 'destino 20 ms envia um RTP');
memberPtimeAssertSame(0, count(array_filter($isolationSocket->packets, static fn(array $packet): bool => $packet['port'] === 23040)), 'destino 40 ms ainda não envia RTP');
memberPtimeAssertSame(substr($pcm30ms, 320), $isolationMedia->members[$id20]['pcmAccumulator'], 'residual independente de 10 ms');
memberPtimeAssertSame($pcm30ms, $isolationMedia->members[$id40]['pcmAccumulator'], 'residual independente de 30 ms');

// Transcoding/resample: três entradas de 10 ms @16kHz viram um RTP PCMA/8kHz de 20 ms e residual de 10 ms.
$resampleSocket = new MemberPtimeCaptureSocket();
$resampleMedia = memberPtimeMedia($resampleSocket);
$resampleId = memberPtimeAdd($resampleMedia, 'b', 24000, 'PCMA', 20);
$pcm16k = memberPtimePcm(480);
for ($frame = 0; $frame < 3; $frame++) {
    $resampleMedia->sendPcmToLeg('b', substr($pcm16k, $frame * 320, 320), 16000);
}
memberPtimeAssertSame(1, count($resampleSocket->packets), 'resample 16k->8k respeita packetização de 20 ms');
memberPtimeAssertSame(160, strlen(memberPtimeDecodeRtp($resampleSocket->packets[0]['data'])['payload']), 'resample gera payload PCMA de 160 samples');
memberPtimeAssertSame(160, strlen($resampleMedia->members[$resampleId]['pcmAccumulator']), 'resample preserva residual de 80 samples');

// VAD e métricas usam a duração real do PCM da origem individual.
$vadSocket = new MemberPtimeCaptureSocket();
$vadMedia = memberPtimeMedia($vadSocket);
$vadId = memberPtimeAdd($vadMedia, 'a', 24510, 'PCMA', 10);
$vadMedia->enableVAD();
$vadMedia->setAudioMetricsEnabled(true);
$processVad = new ReflectionMethod(MediaChannel::class, 'processVAD');
$processVad->invoke($vadMedia, str_repeat("\x00\x00", 80), $vadId, 8000, 1);
$processVad->invoke($vadMedia, str_repeat("\xff\x7f", 80), $vadId, 8000, 1);
memberPtimeAssertSame(true, abs($vadMedia->getAudioMetrics()['silence_time'] - 0.01) < 0.0000001, 'VAD contabiliza 10 ms de silêncio');
memberPtimeAssertSame(true, abs($vadMedia->getAudioMetrics()['voice_time'] - 0.01) < 0.0000001, 'VAD contabiliza 10 ms de voz');

// Silêncio usa samplesPerPacket do destino.
$silenceMethod = new ReflectionMethod(MediaChannel::class, 'makeSilencePayloadForMember');
foreach ([10, 20, 40] as $ptime) {
    $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, $ptime);
    $silence = $silenceMethod->invoke($defaultMedia, ['codec' => 'PCMA', 'frequency' => 8000, 'rtpChannel' => $channel]);
    memberPtimeAssertSame(8 * $ptime, strlen($silence), "silêncio PCMA/$ptime");
}

// DTMF RFC4733 usa o ptime de cada rtpChannel de destino, não o default global.
$dtmfSocket = new MemberPtimeCaptureSocket();
$dtmfMedia = memberPtimeMedia($dtmfSocket);
$dtmfMedia->registerPtCodecs([111 => 'OPUS/48000/1', 102 => 'telephone-event/48000']);
$dtmf10Id = memberPtimeAdd($dtmfMedia, 'a', 25010, 'PCMA', 10);
$dtmf20Id = memberPtimeAdd($dtmfMedia, 'b', 25020, 'PCMU', 20);
$dtmfOpusId = memberPtimeAdd($dtmfMedia, '', 25040, 'OPUS', 40, 48000);
$dtmfMedia->members[$dtmf10Id]['rtpChannel']->sequenceNumber = 100;
$dtmfMedia->members[$dtmf20Id]['rtpChannel']->sequenceNumber = 200;
$dtmfMedia->members[$dtmf10Id]['rtpChannel']->timestamp = 10000;
$dtmfMedia->members[$dtmf20Id]['rtpChannel']->timestamp = 20000;
$dtmfMedia->members[$dtmfOpusId]['rtpChannel']->timestamp = 30000;
Swoole\Coroutine\run(static function () use ($dtmfMedia): void {
    $dtmfMedia->send2833('5');
});
$dtmf10 = array_values(array_filter($dtmfSocket->packets, static fn(array $packet): bool => $packet['port'] === 25010));
$dtmf20 = array_values(array_filter($dtmfSocket->packets, static fn(array $packet): bool => $packet['port'] === 25020));
$dtmfOpus = array_values(array_filter($dtmfSocket->packets, static fn(array $packet): bool => $packet['port'] === 25040));
memberPtimeAssertSame(19, count($dtmf10), 'DTMF ptime 10: 16 progressos + 3 finais');
memberPtimeAssertSame(11, count($dtmf20), 'DTMF ptime 20: 8 progressos + 3 finais');
memberPtimeAssertSame(7, count($dtmfOpus), 'DTMF Opus ptime 40: 4 progressos + 3 finais');
memberPtimeAssertSame(80, unpack('Cevent/Cflags/nduration', memberPtimeDecodeRtp($dtmf10[0]['data'])['payload'])['duration'], 'DTMF passo de 10 ms');
memberPtimeAssertSame(160, unpack('Cevent/Cflags/nduration', memberPtimeDecodeRtp($dtmf20[0]['data'])['payload'])['duration'], 'DTMF passo de 20 ms');
memberPtimeAssertSame(1920, unpack('Cevent/Cflags/nduration', memberPtimeDecodeRtp($dtmfOpus[0]['data'])['payload'])['duration'], 'DTMF Opus usa clock RFC4733 de 48kHz');
memberPtimeAssertSame(119, $dtmfMedia->members[$dtmf10Id]['rtpChannel']->sequenceNumber, 'DTMF sequence contínua no destino 10 ms');
memberPtimeAssertSame(211, $dtmfMedia->members[$dtmf20Id]['rtpChannel']->sequenceNumber, 'DTMF sequence contínua no destino 20 ms');
memberPtimeAssertSame(11280, $dtmfMedia->members[$dtmf10Id]['rtpChannel']->timestamp, 'DTMF timeline final do destino 10 ms');
memberPtimeAssertSame(21280, $dtmfMedia->members[$dtmf20Id]['rtpChannel']->timestamp, 'DTMF timeline final do destino 20 ms');
memberPtimeAssertSame(37680, $dtmfMedia->members[$dtmfOpusId]['rtpChannel']->timestamp, 'DTMF Opus avança 160 ms no clock de 48kHz');

function memberPtimeTestDtmfRelay(int $sourcePtime, int $targetPtime, int $expectedPackets): void
{
    $socket = new MemberPtimeCaptureSocket();
    $media = memberPtimeMedia($socket);
    $sourcePort = 27000 + $sourcePtime;
    memberPtimeAdd($media, 'a', $sourcePort, 'PCMA', $sourcePtime);
    $targetId = memberPtimeAdd($media, 'b', 27100 + $targetPtime, 'PCMU', $targetPtime);
    $targetChannel = $media->members[$targetId]['rtpChannel'];
    $targetChannel->sequenceNumber = 500;
    $targetChannel->timestamp = 60000;
    $forward = new ReflectionMethod(MediaChannel::class, 'forwardDtmfToMembers');
    $step = 8 * $sourcePtime;
    $sequence = 1;
    $durations = range($step, 1280, $step);
    foreach ($durations as $index => $duration) {
        $header = pack('CCnNN', 0x80, ($index === 0 ? 0x80 : 0) | 101, $sequence++, 70000, 0x11223344);
        $packet = new libspech\Rtp\rtpc($header . pack('CCn', 5, 10, $duration));
        $forward->invoke($media, $packet, ['address' => '127.0.0.1', 'port' => $sourcePort], '127.0.0.1:' . $sourcePort);
    }
    for ($end = 0; $end < 3; $end++) {
        $header = pack('CCnNN', 0x80, 101, $sequence++, 70000, 0x11223344);
        $packet = new libspech\Rtp\rtpc($header . pack('CCn', 5, 0x80 | 10, 1280));
        $forward->invoke($media, $packet, ['address' => '127.0.0.1', 'port' => $sourcePort], '127.0.0.1:' . $sourcePort);
    }

    $label = "DTMF relay $sourcePtime->$targetPtime";
    memberPtimeAssertSame($expectedPackets, count($socket->packets), "$label: quantidade repacketizada");
    $first = memberPtimeDecodeRtp($socket->packets[0]['data']);
    $last = memberPtimeDecodeRtp($socket->packets[array_key_last($socket->packets)]['data']);
    memberPtimeAssertSame(8 * $targetPtime, unpack('Cevent/Cflags/nduration', $first['payload'])['duration'], "$label: primeiro passo do destino");
    memberPtimeAssertSame(60000, $first['timestamp'], "$label: timestamp RFC4733 congelado");
    memberPtimeAssertSame(500 + $expectedPackets - 1, $last['sequence'], "$label: sequence contínua");
    memberPtimeAssertSame(61280, $targetChannel->timestamp, "$label: timeline de áudio avança 160 ms");
}

memberPtimeTestDtmfRelay(10, 20, 11);
memberPtimeTestDtmfRelay(20, 10, 19);
memberPtimeTestDtmfRelay(20, 60, 5);

// Codecs stateful mantêm instâncias por membro e Opus separa encoder/decoder.
$stateSocket = new MemberPtimeCaptureSocket();
$stateMedia = memberPtimeMedia($stateSocket);
$opusA = memberPtimeAdd($stateMedia, 'a', 26110, 'OPUS', 10, 48000);
$opusB = memberPtimeAdd($stateMedia, 'b', 26120, 'OPUS', 20, 48000);
$opus40 = memberPtimeAdd($stateMedia, '', 26140, 'OPUS', 40, 48000);
$opus60 = memberPtimeAdd($stateMedia, '', 26160, 'OPUS', 60, 48000);
memberPtimeAssertSame(480, $stateMedia->members[$opusA]['samplesPerPacket'], 'Opus 10 ms tem 480 samples');
memberPtimeAssertSame(960, $stateMedia->members[$opusB]['samplesPerPacket'], 'Opus 20 ms tem 960 samples');
memberPtimeAssertSame(1920, $stateMedia->members[$opus40]['samplesPerPacket'], 'Opus 40 ms tem 1920 samples');
memberPtimeAssertSame(2880, $stateMedia->members[$opus60]['samplesPerPacket'], 'Opus 60 ms tem 2880 samples');
memberPtimeAssertSame(false, $stateMedia->members[$opusA]['opusEncoder'] === $stateMedia->members[$opusA]['opusDecoder'], 'Opus separa encoder e decoder do membro');
memberPtimeAssertSame(false, $stateMedia->members[$opusA]['opusEncoder'] === $stateMedia->members[$opusB]['opusEncoder'], 'Opus não compartilha encoder entre membros');
$stateMedia->members[$opusB]['rtpChannel']->timestamp = 40000;
$stateMedia->sendPcmToLeg('b', memberPtimePcm(480), 48000);
memberPtimeAssertSame(0, count($stateSocket->packets), 'Opus 10->20 acumula o primeiro frame');
$stateMedia->sendPcmToLeg('b', memberPtimePcm(480, 480), 48000);
memberPtimeAssertSame(1, count($stateSocket->packets), 'Opus 10->20 envia ao completar 960 samples');
memberPtimeAssertSame(true, strlen(memberPtimeDecodeRtp($stateSocket->packets[0]['data'])['payload']) > 0, 'Opus 20 ms gera payload');
memberPtimeAssertSame(40960, $stateMedia->members[$opusB]['rtpChannel']->timestamp, 'Opus 20 ms avança 960 samples');
$g729A = memberPtimeAdd($stateMedia, '', 26210, 'G729', 10);
$g729B = memberPtimeAdd($stateMedia, '', 26220, 'G729', 20);
memberPtimeAssertSame(false, $stateMedia->members[$g729A]['bcg729Channel'] === $stateMedia->members[$g729B]['bcg729Channel'], 'G729 não compartilha encoder entre membros');

// Caminhos stateful/L16 também recebem exatamente um frame PCM no ptime do destino.
$codecSocket = new MemberPtimeCaptureSocket();
$codecMedia = memberPtimeMedia($codecSocket);
$g729Send = memberPtimeAdd($codecMedia, 'a', 26320, 'G729', 20);
$codecMedia->members[$g729Send]['rtpChannel']->timestamp = 30000;
$codecMedia->sendPcmToLeg('a', memberPtimePcm(160), 8000);
memberPtimeAssertSame(20, strlen(memberPtimeDecodeRtp($codecSocket->packets[0]['data'])['payload']), 'G729/20 gera dois frames de 10 bytes no mesmo RTP');
memberPtimeAssertSame(30160, $codecMedia->members[$g729Send]['rtpChannel']->timestamp, 'G729/20 avança 160 samples');

$l16Socket = new MemberPtimeCaptureSocket();
$l16Media = memberPtimeMedia($l16Socket);
$l16Id = memberPtimeAdd($l16Media, 'b', 26410, 'L16', 10, 16000, 2);
$l16Media->sendPcmToLeg('b', memberPtimePcm(160 * 2), 16000, 2);
memberPtimeAssertSame(640, strlen(memberPtimeDecodeRtp($l16Socket->packets[0]['data'])['payload']), 'L16/16k/stereo/10 gera frame completo');
memberPtimeAssertSame(160, $l16Media->members[$l16Id]['samplesPerPacket'], 'L16/16k/10 tem 160 samples por canal');

echo "PASS: ptime individual, accumulator PCM, RTP, silêncio, DTMF e legado de 20 ms validados.\n";
