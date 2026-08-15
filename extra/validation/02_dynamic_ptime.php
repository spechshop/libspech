<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpChannel;

final class PtimeCaptureSocket extends SocketMutable
{
    /** @var list<string> */
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
        $this->packets[] = $data;
        return strlen($data);
    }
}

function ptimeAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s (esperado: %s, obtido: %s)',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function ptimeAssertNear(float $expected, float $actual, string $message): void
{
    if (abs($expected - $actual) > 0.0000001) {
        throw new RuntimeException(sprintf(
            '%s (esperado: %.8f, obtido: %.8f)',
            $message,
            $expected,
            $actual
        ));
    }
}

/** @return array{sequence:int,timestamp:int,payload:string} */
function ptimeDecodeRtp(string $packet): array
{
    $header = unpack('Cfirst/Csecond/nsequence/Ntimestamp/Nssrc', substr($packet, 0, 12));
    return [
        'sequence' => $header['sequence'],
        'timestamp' => $header['timestamp'],
        'payload' => substr($packet, 12),
    ];
}

function ptimeMediaWithoutConstructor(): MediaChannel
{
    $reflection = new ReflectionClass(MediaChannel::class);
    /** @var MediaChannel $media */
    $media = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('onDestructCallable')->setValue($media, static function (): void {
    });
    return $media;
}

function ptimeInvokePrivate(MediaChannel $media, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod($media, $method);
    return $reflection->invoke($media, ...$arguments);
}

function ptimeTestCodec(int $payloadType, int $ptimeMs): void
{
    $samples = 8000 * $ptimeMs / 1000;
    $pcm = '';
    for ($sample = 0; $sample < $samples; $sample++) {
        $value = (int)round(sin(2 * M_PI * 440 * ($sample / 8000)) * 12000);
        $pcm .= pack('v', $value & 0xffff);
    }

    if ($payloadType === rtpChannel::PAYLOAD_PCMA) {
        $payload = encodePcmToPcma($pcm);
        $decoded = decodePcmaToPcm($payload);
        $codec = 'PCMA';
    } else {
        $payload = encodePcmToPcmu($pcm);
        $decoded = decodePcmuToPcm($payload);
        $codec = 'PCMU';
    }

    ptimeAssertSame($samples, strlen($payload), "$codec/$ptimeMs: tamanho do payload");
    ptimeAssertSame($samples * 2, strlen($decoded), "$codec/$ptimeMs: tamanho após decode");

    $channel = new rtpChannel($payloadType, 8000, $ptimeMs, 0x10203040);
    ptimeAssertSame($samples, $channel->samplesPerPacket, "$codec/$ptimeMs: samples por pacote");
    $channel->timestamp = 1000;
    $first = ptimeDecodeRtp($channel->buildAudioPacket($payload));
    $second = ptimeDecodeRtp($channel->buildAudioPacket($payload));
    ptimeAssertSame(1000, $first['timestamp'], "$codec/$ptimeMs: timestamp inicial");
    ptimeAssertSame(1000 + $samples, $second['timestamp'], "$codec/$ptimeMs: incremento de timestamp");
    ptimeAssertSame($samples, strlen($first['payload']), "$codec/$ptimeMs: payload no RTP");

    $channel->timestamp = 5000;
    $dtmfPackets = $channel->generateDtmfSequence('5', max(120, $ptimeMs));
    $firstDtmf = ptimeDecodeRtp($dtmfPackets[0]);
    $dtmfPayload = unpack('Cevent/Cflags/nduration', $firstDtmf['payload']);
    ptimeAssertSame($samples, $dtmfPayload['duration'], "$codec/$ptimeMs: primeiro passo DTMF");
}

$ptimes = [10, 20, 30, 40, 60];

$defaultMedia = ptimeMediaWithoutConstructor();
ptimeAssertSame(20, $defaultMedia->getPacketTime(), 'MediaChannel mantém ptime legado por padrão');
$defaultMedia->addMember([
    'address' => '127.0.0.1',
    'port' => 19999,
    'codec' => 'PCMU',
    'pt' => rtpChannel::PAYLOAD_PCMU,
    'frequency' => 8000,
    'config' => [],
]);
ptimeAssertSame(20, $defaultMedia->members['127.0.0.1:19999']['rtpChannel']->packetTimeMs, 'addMember mantém ptime legado');
ptimeAssertSame(160, $defaultMedia->members['127.0.0.1:19999']['rtpChannel']->samplesPerPacket, 'addMember mantém 160 samples no legado');

$defaultRtp = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000);
ptimeAssertSame(20, $defaultRtp->packetTimeMs, 'rtpChannel mantém ptime legado por padrão');
ptimeAssertSame(160, $defaultRtp->samplesPerPacket, 'rtpChannel mantém 160 samples no legado');
$defaultRtp->timestamp = 777;
$defaultRtp->setPacketTime(40);
ptimeAssertSame(40, $defaultRtp->packetTimeMs, 'rtpChannel permite atualizar ptime');
ptimeAssertSame(320, $defaultRtp->samplesPerPacket, 'rtpChannel recalcula samples ao atualizar ptime');
ptimeAssertSame(777, $defaultRtp->timestamp, 'rtpChannel preserva timestamp ao atualizar ptime');

foreach ($ptimes as $ptimeMs) {
    ptimeTestCodec(rtpChannel::PAYLOAD_PCMA, $ptimeMs);
    ptimeTestCodec(rtpChannel::PAYLOAD_PCMU, $ptimeMs);

    $samples = 8000 * $ptimeMs / 1000;
    $media = ptimeMediaWithoutConstructor();
    $media->setPacketTime($ptimeMs);
    ptimeAssertSame($ptimeMs, $media->getPacketTime(), "MediaChannel/$ptimeMs: getter");

    $media->addMember([
        'address' => '127.0.0.1',
        'port' => 20000 + $ptimeMs,
        'codec' => 'PCMA',
        'pt' => rtpChannel::PAYLOAD_PCMA,
        'frequency' => 8000,
        'config' => [],
    ]);
    $member = $media->members['127.0.0.1:' . (20000 + $ptimeMs)];
    ptimeAssertSame($ptimeMs, $member['rtpChannel']->packetTimeMs, "MediaChannel/$ptimeMs: canal de addMember");
    ptimeAssertSame($samples, $member['rtpChannel']->samplesPerPacket, "MediaChannel/$ptimeMs: samples de addMember");

    foreach (['PCMA' => "\xD5", 'PCMU' => "\xFF"] as $codec => $silenceByte) {
        $payloadType = $codec === 'PCMA' ? rtpChannel::PAYLOAD_PCMA : rtpChannel::PAYLOAD_PCMU;
        $silence = ptimeInvokePrivate($media, 'makeSilencePayloadForMember', [
            'codec' => $codec,
            'frequency' => 8000,
            'rtpChannel' => new rtpChannel($payloadType, 8000, $ptimeMs),
        ]);
        ptimeAssertSame($samples, strlen($silence), "$codec/$ptimeMs: tamanho do silêncio");
        ptimeAssertSame(str_repeat($silenceByte, $samples), $silence, "$codec/$ptimeMs: conteúdo do silêncio");
    }

    $media->enableVAD();
    $media->setAudioMetricsEnabled(true);
    ptimeInvokePrivate($media, 'processVAD', str_repeat("\x00\x00", $samples), 'silence');
    ptimeInvokePrivate($media, 'processVAD', str_repeat("\xff\x7f", $samples), 'voice');
    $metrics = $media->getAudioMetrics();
    ptimeAssertNear($ptimeMs / 1000, $metrics['silence_time'], "MediaChannel/$ptimeMs: métrica de silêncio");
    ptimeAssertNear($ptimeMs / 1000, $metrics['voice_time'], "MediaChannel/$ptimeMs: métrica de voz");

    $captureSocket = new PtimeCaptureSocket();
    $dtmfMedia = ptimeMediaWithoutConstructor();
    $dtmfMedia->socket = $captureSocket;
    $dtmfMedia->setPacketTime($ptimeMs);
    $dtmfChannel = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, $ptimeMs, 0x55667788);
    $dtmfChannel->timestamp = 9000;
    $dtmfMedia->members['dtmf'] = [
        'address' => '127.0.0.1',
        'port' => 30000,
        'frequency' => 8000,
        'rtpChannel' => $dtmfChannel,
    ];
    Swoole\Coroutine\run(static function () use ($dtmfMedia): void {
        $dtmfMedia->send2833('5');
    });
    $expectedProgressPackets = (int)ceil(160 / $ptimeMs);
    ptimeAssertSame($expectedProgressPackets + 3, count($captureSocket->packets), "DTMF/$ptimeMs: quantidade de pacotes");
    $firstDtmf = ptimeDecodeRtp($captureSocket->packets[0]);
    $firstDtmfPayload = unpack('Cevent/Cflags/nduration', $firstDtmf['payload']);
    ptimeAssertSame(min(1280, $samples), $firstDtmfPayload['duration'], "DTMF/$ptimeMs: passo sem 160 fixo");
    $lastDtmf = ptimeDecodeRtp($captureSocket->packets[array_key_last($captureSocket->packets)]);
    $lastDtmfPayload = unpack('Cevent/Cflags/nduration', $lastDtmf['payload']);
    ptimeAssertSame(1280, $lastDtmfPayload['duration'], "DTMF/$ptimeMs: duração total preservada");
    ptimeAssertSame(10280, $dtmfChannel->timestamp, "DTMF/$ptimeMs: timeline final");
}

$updatedMedia = ptimeMediaWithoutConstructor();
$updatedMedia->rtpChans[1] = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, 20);
$updatedMedia->members['existing'] = [
    'rtpChannel' => new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 20),
];
$updatedMedia->rtpChans[1]->timestamp = 123456;
$updatedMedia->members['existing']['rtpChannel']->timestamp = 654321;
$updatedMedia->setPacketTime(30);
ptimeAssertSame(30, $updatedMedia->rtpChans[1]->packetTimeMs, 'setPacketTime atualiza canal criado no recebimento');
ptimeAssertSame(240, $updatedMedia->rtpChans[1]->samplesPerPacket, 'setPacketTime recalcula samples do recebimento');
ptimeAssertSame(30, $updatedMedia->members['existing']['rtpChannel']->packetTimeMs, 'setPacketTime atualiza membro existente');
ptimeAssertSame(123456, $updatedMedia->rtpChans[1]->timestamp, 'setPacketTime preserva timestamp do recebimento');
ptimeAssertSame(654321, $updatedMedia->members['existing']['rtpChannel']->timestamp, 'setPacketTime preserva timestamp do membro');

foreach ([0, -10] as $invalidPtime) {
    $previousPtime = $updatedMedia->getPacketTime();
    try {
        $updatedMedia->setPacketTime($invalidPtime);
        throw new RuntimeException("ptime inválido $invalidPtime foi aceito pelo MediaChannel");
    } catch (InvalidArgumentException) {
    }
    ptimeAssertSame($previousPtime, $updatedMedia->getPacketTime(), 'ptime inválido não altera o MediaChannel');

    try {
        $invalidRtp = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, 20);
        $invalidRtp->setPacketTime($invalidPtime);
        throw new RuntimeException("ptime inválido $invalidPtime foi aceito pelo rtpChannel");
    } catch (InvalidArgumentException) {
    }
}

echo "OK: ptime dinâmico validado para 10, 20, 30, 40 e 60 ms em PCMA/8000 e PCMU/8000.\n";
