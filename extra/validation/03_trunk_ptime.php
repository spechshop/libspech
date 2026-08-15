<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpChannel;
use libspech\Sip\trunkController;

final class TrunkPtimeCaptureSocket extends SocketMutable
{
    /** @var list<string> */
    public array $packets = [];

    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public function sendto(string $addr, int $port, string $data): int|false
    {
        $this->packets[] = $data;
        return strlen($data);
    }
}

function trunkPtimeAssertSame(mixed $expected, mixed $actual, string $message): void
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

function trunkPtimeAssertNear(float $expected, float $actual, string $message): void
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

function trunkPtimeWithoutConstructor(): trunkController
{
    $reflection = new ReflectionClass(trunkController::class);
    /** @var trunkController $trunk */
    $trunk = $reflection->newInstanceWithoutConstructor();
    return $trunk;
}

function trunkPtimeMediaWithoutConstructor(): MediaChannel
{
    $reflection = new ReflectionClass(MediaChannel::class);
    /** @var MediaChannel $media */
    $media = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('onDestructCallable')->setValue($media, static function (): void {
    });
    return $media;
}

function trunkPtimeAudioCallbackState(trunkController $trunk): array
{
    $property = new ReflectionProperty($trunk, 'audioFileHandle');
    $callback = $property->getValue($trunk);
    if (!$callback instanceof Closure) {
        throw new RuntimeException('Callback de playback não foi configurado');
    }
    return (new ReflectionFunction($callback))->getStaticVariables();
}

function trunkPtimeInvokePrivate(trunkController $trunk, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($trunk, $method))->invoke($trunk, ...$arguments);
}

$ptimes = [10, 20, 30, 40, 60, 120];
$trunk = trunkPtimeWithoutConstructor();
trunkPtimeAssertSame(20, $trunk->getPacketTime(), 'trunkController mantém ptime legado por padrão');

$media = trunkPtimeMediaWithoutConstructor();
$media->rtpChans[1] = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, 20);
$trunk->mediaChannel = $media;
$trunk->setPacketTime(30);
trunkPtimeAssertSame(30, $trunk->getPacketTime(), 'setter atualiza o trunkController');
trunkPtimeAssertSame(30, $media->getPacketTime(), 'setter propaga para o MediaChannel');
trunkPtimeAssertSame(240, $media->rtpChans[1]->samplesPerPacket, 'setter propaga para canais RTP existentes');

foreach ([0, -10] as $invalidPtime) {
    try {
        $trunk->setPacketTime($invalidPtime);
        throw new RuntimeException("ptime inválido $invalidPtime foi aceito");
    } catch (InvalidArgumentException) {
    }
    trunkPtimeAssertSame(30, $trunk->getPacketTime(), 'ptime inválido não altera o trunkController');
    trunkPtimeAssertSame(30, $media->getPacketTime(), 'ptime inválido não altera o MediaChannel');
}

$opusValidationTrunk = trunkPtimeWithoutConstructor();
$opusValidationTrunk->codecName = 'OPUS';
try {
    $opusValidationTrunk->setPacketTime(30);
    throw new RuntimeException('ptime de 30 ms foi aceito para Opus');
} catch (InvalidArgumentException) {
}
trunkPtimeAssertSame(20, $opusValidationTrunk->getPacketTime(), 'ptime incompatível com Opus não altera a configuração');

$g729ValidationTrunk = trunkPtimeWithoutConstructor();
$g729ValidationTrunk->codecName = 'G729';
try {
    $g729ValidationTrunk->setPacketTime(15);
    throw new RuntimeException('ptime de 15 ms foi aceito para G.729');
} catch (InvalidArgumentException) {
}
trunkPtimeAssertSame(20, $g729ValidationTrunk->getPacketTime(), 'ptime incompatível com G.729 não altera a configuração');

$pcmaMaxPtimeTrunk = trunkPtimeWithoutConstructor();
$pcmaMaxPtimeMedia = trunkPtimeMediaWithoutConstructor();
$pcmaMaxPtimeTrunk->mediaChannel = $pcmaMaxPtimeMedia;
$pcmaMaxPtimeTrunk->setPacketTime(120);
$pcmaMaxPtimeTrunk->setupForIncoming(8, 'PCMA', 8000, [
    'a' => ['rtpmap:8 PCMA/8000', 'maxptime:30'],
]);
trunkPtimeAssertSame(120, $pcmaMaxPtimeTrunk->getConfiguredPacketTime(), 'maxptime preserva o ptime solicitado');
trunkPtimeAssertSame(30, $pcmaMaxPtimeTrunk->getPacketTime(), 'maxptime limita PCMA ao máximo remoto');
trunkPtimeAssertSame(30, $pcmaMaxPtimeMedia->getPacketTime(), 'maxptime é propagado ao MediaChannel');
trunkPtimeAssertSame(30, $pcmaMaxPtimeTrunk->getRemoteMaxPacketTime(), 'maxptime remoto fica disponível para consulta');

$opusMaxPtimeTrunk = trunkPtimeWithoutConstructor();
$opusMaxPtimeTrunk->setPacketTime(40);
$opusMaxPtimeTrunk->setupForIncoming(111, 'OPUS', 48000, [
    'a' => ['rtpmap:111 opus/48000/2', 'MAXPTIME:30'],
]);
trunkPtimeAssertSame(20, $opusMaxPtimeTrunk->getPacketTime(), 'maxptime escolhe o maior frame Opus compatível');

$g729MaxPtimeTrunk = trunkPtimeWithoutConstructor();
$g729MaxPtimeTrunk->setPacketTime(60);
$g729MaxPtimeTrunk->setupForIncoming(18, 'G729', 8000, [
    'a' => ['rtpmap:18 G729/8000', 'a=maxptime:35.9'],
]);
trunkPtimeAssertSame(30, $g729MaxPtimeTrunk->getPacketTime(), 'maxptime respeita frames de 10 ms do G.729');

$pcmaMaxPtimeTrunk->setupForIncoming(8, 'PCMA', 8000, [
    'a' => ['rtpmap:8 PCMA/8000'],
]);
trunkPtimeAssertSame(null, $pcmaMaxPtimeTrunk->getRemoteMaxPacketTime(), 'SDP sem maxptime remove o limite remoto');
trunkPtimeAssertSame(120, $pcmaMaxPtimeTrunk->getPacketTime(), 'remoção de maxptime restaura o ptime solicitado');

foreach ([5, 10, 20, 40, 60, 120] as $ptimeMs) {
    $waitEnergyTrunk = trunkPtimeWithoutConstructor();
    $waitEnergyTrunk->setPacketTime($ptimeMs);
    $samplesPerChannel = 48000 * $ptimeMs / 1000;
    $silencePcm = str_repeat("\x00\x00", $samplesPerChannel * 2);
    $voicePcm = str_repeat("\xff\x7f", $samplesPerChannel * 2);
    trunkPtimeAssertSame(1.0, $waitEnergyTrunk->volumeAverage($silencePcm, 48000, 2), "waitSilence/$ptimeMs: silêncio estéreo");
    trunkPtimeAssertSame(true, $waitEnergyTrunk->volumeAverage($voicePcm, 48000, 2) > 1.1, "waitSilence/$ptimeMs: voz estéreo");
}

$waitStateTrunk = trunkPtimeWithoutConstructor();
$waitStateTrunk->setPacketTime(10);
$silence10ms = str_repeat("\x00\x00", 80);
$voice10ms = str_repeat("\xff\x7f", 80);

$waitStateTrunk->waitingSilence = true;
$waitStateTrunk->waitingSilenceType = true;
$waitStateTrunk->waitingSilenceTime = 0.1;
$waitStateTrunk->waitingSilenceStart = microtime(true) - 0.2;
trunkPtimeInvokePrivate($waitStateTrunk, 'processWaitSilenceFrame', $silence10ms, 8000, 1);
trunkPtimeAssertSame(false, $waitStateTrunk->waitingSilence, 'waitSilence(true) conclui após silêncio contínuo');
trunkPtimeAssertSame(true, $waitStateTrunk->waitingSilenceSuccess, 'waitSilence(true) retorna sucesso para silêncio');

$waitStateTrunk->waitingSilence = true;
$waitStateTrunk->waitingSilenceType = true;
$waitStateTrunk->waitingSilenceTime = 0.1;
$waitStateTrunk->waitingSilenceStart = microtime(true) - 0.2;
$waitStateTrunk->waitingSilenceSuccess = false;
trunkPtimeInvokePrivate($waitStateTrunk, 'processWaitSilenceFrame', $voice10ms, 8000, 1);
trunkPtimeAssertSame(true, $waitStateTrunk->waitingSilence, 'voz mantém espera por silêncio ativa');
trunkPtimeAssertSame(false, $waitStateTrunk->waitingSilenceSuccess, 'voz não conclui espera por silêncio');
trunkPtimeAssertSame(true, $waitStateTrunk->waitingSilenceStart > microtime(true) - 0.05, 'voz reinicia janela de silêncio');

$waitStateTrunk->waitingSilence = true;
$waitStateTrunk->waitingSilenceType = false;
$waitStateTrunk->waitingSilenceTime = 1.0;
$waitStateTrunk->waitingSilenceStart = microtime(true);
$waitStateTrunk->waitingSilenceSuccess = false;
trunkPtimeInvokePrivate($waitStateTrunk, 'processWaitSilenceFrame', $voice10ms, 8000, 1);
trunkPtimeAssertSame(false, $waitStateTrunk->waitingSilence, 'waitSilence(false) conclui ao detectar voz');
trunkPtimeAssertSame(true, $waitStateTrunk->waitingSilenceSuccess, 'waitSilence(false) retorna sucesso para voz');

$voiceTimeoutResult = null;
$voiceTimeoutTrunk = trunkPtimeWithoutConstructor();
$voiceTimeoutTrunk->setPacketTime(10);
$voiceTimeoutTrunk->callActive = true;
$voiceTimeoutTrunk->receiveBye = false;
Swoole\Coroutine\run(static function () use ($voiceTimeoutTrunk, &$voiceTimeoutResult): void {
    $voiceTimeoutResult = $voiceTimeoutTrunk->waitSilence(false, 0.02);
});
trunkPtimeAssertSame(false, $voiceTimeoutResult, 'waitSilence(false) retorna false no timeout sem voz');

try {
    $voiceTimeoutTrunk->waitSilence(true, 0.0);
    throw new RuntimeException('waitSilence aceitou tempo inválido');
} catch (InvalidArgumentException) {
}

$wavPath = tempnam(sys_get_temp_dir(), 'libspech-ptime-');
if ($wavPath === false) {
    throw new RuntimeException('Não foi possível criar WAV temporário');
}
$wavPcm = str_repeat("\x00\x00", 8000);
file_put_contents($wavPath, \libspech\Sip\waveHead3(strlen($wavPcm), 8000, 1) . $wavPcm);

try {
    foreach ($ptimes as $ptimeMs) {
        $expectedSamples = 8000 * $ptimeMs / 1000;
        $expectedPcmBytes = $expectedSamples * 2;

        $ptimeTrunk = trunkPtimeWithoutConstructor();
        $ptimeTrunk->setPacketTime($ptimeMs);

        $wav = $ptimeTrunk->loadWavFile($wavPath);
        trunkPtimeAssertSame($expectedPcmBytes, $wav['chunkSize'], "WAV/$ptimeMs: chunk PCM");

        $ptimeTrunk->defineAudioFile($wavPath);
        $callbackState = trunkPtimeAudioCallbackState($ptimeTrunk);
        trunkPtimeAssertSame($expectedPcmBytes, $callbackState['chunkSize'], "playback/$ptimeMs: chunk PCM");
        trunkPtimeAssertSame($ptimeMs, $callbackState['configuredPacketTime'], "playback/$ptimeMs: ptime capturado");

        $ptimeTrunk->enableVAD();
        $ptimeTrunk->processVAD(str_repeat("\x00\x00", $expectedSamples), 'silence');
        $ptimeTrunk->processVAD(str_repeat("\xff\x7f", $expectedSamples), 'voice');
        trunkPtimeAssertNear($ptimeMs / 1000, $ptimeTrunk->audioMetrics['silence_time'], "métrica/$ptimeMs: silêncio");
        trunkPtimeAssertNear($ptimeMs / 1000, $ptimeTrunk->audioMetrics['voice_time'], "métrica/$ptimeMs: voz");
    }

    $lateChangeTrunk = trunkPtimeWithoutConstructor();
    $lateChangeTrunk->setPacketTime(20);
    $lateChangeTrunk->defineAudioFile($wavPath);
    $captureSocket = new TrunkPtimeCaptureSocket();
    $playbackMedia = trunkPtimeMediaWithoutConstructor();
    $playbackMedia->socket = $captureSocket;
    $playbackMedia->members['127.0.0.1:30000'] = [
        'address' => '127.0.0.1',
        'port' => 30000,
        'codec' => 'PCMA',
        'channels' => 1,
        'config' => [],
        'rtpChannel' => new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, 20),
    ];
    $lateChangeTrunk->mediaChannel = $playbackMedia;
    $lateChangeTrunk->codecName = 'PCMA';
    $lateChangeTrunk->frequencyCall = 8000;
    $lateChangeTrunk->callActive = true;
    $lateChangeTrunk->setPacketTime(60);

    $callbackProperty = new ReflectionProperty($lateChangeTrunk, 'audioFileHandle');
    $playbackCallback = $callbackProperty->getValue($lateChangeTrunk);
    $playbackCallback(['address' => '127.0.0.1', 'port' => 30000], $lateChangeTrunk);
    $lateCallbackState = trunkPtimeAudioCallbackState($lateChangeTrunk);
    trunkPtimeAssertSame(960, $lateCallbackState['chunkSize'], 'playback recalcula chunk após mudança tardia para 60 ms');
    trunkPtimeAssertSame(60, $lateCallbackState['configuredPacketTime'], 'playback acompanha mudança tardia de ptime');
    trunkPtimeAssertSame(492, strlen($captureSocket->packets[0]), 'playback envia RTP com payload PCMA de 60 ms');
} finally {
    unlink($wavPath);
}

$opusWavPath = tempnam(sys_get_temp_dir(), 'libspech-opus-ptime-');
if ($opusWavPath === false) {
    throw new RuntimeException('Não foi possível criar WAV Opus temporário');
}
$opusSourcePcm = str_repeat("\x00\x00", 44100 * 2);
file_put_contents(
    $opusWavPath,
    \libspech\Sip\waveHead3(strlen($opusSourcePcm), 44100, 2) . $opusSourcePcm
);

try {
    foreach ([1, 2] as $opusChannels) {
        $opusTrunk = trunkPtimeWithoutConstructor();
        $opusTrunk->disableAudioMemorySharing();
        $opusTrunk->setPacketTime(10);
        $opusTrunk->defineAudioFile($opusWavPath);

        $opusCapture = new TrunkPtimeCaptureSocket();
        $opusMedia = trunkPtimeMediaWithoutConstructor();
        $opusMedia->socket = $opusCapture;
        $opusRtp = new rtpChannel(111, 48000, 10);
        $opusRtp->timestamp = 1000;
        $opusMedia->members['127.0.0.1:31000'] = [
            'address' => '127.0.0.1',
            'port' => 31000,
            'codec' => 'OPUS',
            'channels' => $opusChannels,
            'config' => [],
            'rtpChannel' => $opusRtp,
        ];
        $opusTrunk->mediaChannel = $opusMedia;
        $opusTrunk->codecName = 'OPUS';
        $opusTrunk->frequencyCall = 48000;
        $opusTrunk->callActive = true;

        $callbackProperty = new ReflectionProperty($opusTrunk, 'audioFileHandle');
        $opusCallback = $callbackProperty->getValue($opusTrunk);
        $opusCallback(['address' => '127.0.0.1', 'port' => 31000], $opusTrunk);

        trunkPtimeAssertSame(1, count($opusCapture->packets), "Opus {$opusChannels}ch: pacote enviado");
        trunkPtimeAssertSame(1480, $opusRtp->timestamp, "Opus {$opusChannels}ch: timestamp de 10 ms");
        trunkPtimeAssertSame(true, strlen($opusCapture->packets[0]) > 12, "Opus {$opusChannels}ch: payload codificado");
    }
} finally {
    unlink($opusWavPath);
}

$sdpTrunk = trunkPtimeWithoutConstructor();
$sdpTrunk->setPacketTime(40);
$sdpTrunk->callerId = '1000';
$sdpTrunk->username = '1000';
$sdpTrunk->localIp = '127.0.0.1';
$sdpTrunk->host = '127.0.0.1';
$sdpTrunk->port = 5060;
$sdpTrunk->ssrc = 1234;
$sdpTrunk->callId = 'ptime-test';
$sdpTrunk->socketPortListen = 5062;
$sdpTrunk->csq = 1;
$sdpTrunk->mapLearn = [
    8 => ['rtpmap:8 PCMA/8000'],
    101 => ['rtpmap:101 telephone-event/8000', 'fmtp:101 0-16'],
];
$sdpTrunk->rtpSocket = new class {
    public function getsockname(): array
    {
        return ['address' => '127.0.0.1', 'port' => 20000];
    }
};
$invite = $sdpTrunk->modelInvite('2000');
trunkPtimeAssertSame(true, in_array('ptime:40', $invite['sdp']['a'], true), 'SDP anuncia o ptime configurado');
trunkPtimeAssertSame(false, in_array('minptime:40', $invite['sdp']['a'], true), 'SDP não anuncia minptime genérico inválido');

echo "OK: trunkController validado com ptime 10, 20, 30, 40, 60 e 120 ms.\n";
