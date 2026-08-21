<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpChannel;
use libspech\Sip\trunkController;

final class TrunkPacingCaptureSocket extends SocketMutable
{
    /** @var list<int> */
    public array $sendTimesNs = [];
    /** @var list<string> */
    public array $packets = [];
    public ?MediaChannel $media = null;
    public int $targetPackets = 0;
    public mixed $onSend = null;

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
        $this->sendTimesNs[] = hrtime(true);
        $this->packets[] = $data;

        if (is_callable($this->onSend)) {
            ($this->onSend)(count($this->packets));
        }
        if ($this->media !== null && count($this->packets) >= $this->targetPackets) {
            $this->media->active = false;
        }

        return strlen($data);
    }
}

function pacingAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pacingAssertSame(mixed $expected, mixed $actual, string $message): void
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

/** @return array{sequence:int,timestamp:int,payload:string} */
function pacingDecodeRtp(string $packet): array
{
    $header = unpack('Cfirst/Csecond/nsequence/Ntimestamp/Nssrc', substr($packet, 0, 12));
    return [
        'sequence' => $header['sequence'],
        'timestamp' => $header['timestamp'],
        'payload' => substr($packet, 12),
    ];
}

function pacingMediaWithoutConstructor(): MediaChannel
{
    $reflection = new ReflectionClass(MediaChannel::class);
    /** @var MediaChannel $media */
    $media = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('onDestructCallable')->setValue($media, static function (): void {
    });
    return $media;
}

function pacingTrunkWithoutConstructor(): trunkController
{
    $reflection = new ReflectionClass(trunkController::class);
    /** @var trunkController $trunk */
    $trunk = $reflection->newInstanceWithoutConstructor();
    return $trunk;
}

/**
 * @return array{
 *   codec:string,ptime_ms:int,processing_ms:int,mean_ms:float,min_ms:float,max_ms:float,
 *   elapsed_ms:float,expected_elapsed_ms:float,payload_bytes:int,timestamp_delta:int,packets:list<string>,times:list<int>
 * }
 */
function pacingRunCodec(
    string $wavPath,
    string $codec,
    int $ptimeMs,
    int $packetCount,
    int $processingMs = 0,
    mixed $onSendFactory = null,
    bool $validateStaticPtime = true
): array {
    $codec = strtoupper($codec);
    [$payloadType, $frequency] = match ($codec) {
        'PCMA' => [rtpChannel::PAYLOAD_PCMA, 8000],
        'G729' => [rtpChannel::PAYLOAD_G729, 8000],
        'OPUS' => [111, 48000],
        default => throw new InvalidArgumentException("Codec de teste não suportado: {$codec}"),
    };

    $trunk = pacingTrunkWithoutConstructor();
    $trunk->disableAudioMemorySharing();
    $trunk->codecName = $codec;
    $trunk->frequencyCall = $frequency;
    $trunk->audioRemoteIp = '127.0.0.1';
    $trunk->audioRemotePort = 32000 + $ptimeMs;
    $trunk->callActive = true;
    $trunk->setPacketTime($ptimeMs);
    $trunk->defineAudioFile($wavPath);

    $media = pacingMediaWithoutConstructor();
    $socket = new TrunkPacingCaptureSocket();
    $socket->media = $media;
    $socket->targetPackets = $packetCount;
    $media->socket = $socket;
    $media->setPacketTime($ptimeMs);

    $id = $trunk->audioRemoteIp . ':' . $trunk->audioRemotePort;
    $rtp = new rtpChannel($payloadType, $frequency, $ptimeMs, 0x10203040);
    $rtp->sequenceNumber = 1000;
    $rtp->timestamp = 500000;
    $media->members[$id] = [
        'address' => $trunk->audioRemoteIp,
        'port' => $trunk->audioRemotePort,
        'codec' => $codec,
        'frequency' => $frequency,
        'channels' => 1,
        'config' => [],
        'rtpChannel' => $rtp,
    ];
    $trunk->mediaChannel = $media;

    if ($onSendFactory !== null) {
        $socket->onSend = $onSendFactory($trunk, $media, $socket);
    }

    if ($processingMs > 0) {
        $audioHandleProperty = new ReflectionProperty($trunk, 'audioFileHandle');
        $audioHandle = $audioHandleProperty->getValue($trunk);
        pacingAssert($audioHandle instanceof Closure, 'Callback de áudio não foi configurado');
        $audioHandleProperty->setValue(
            $trunk,
            static function (array $peer, trunkController $phone) use ($audioHandle, $processingMs): void {
                // Simula geração/resample/encode custando alguns milissegundos.
                Swoole\Coroutine::sleep($processingMs / 1000);
                $audioHandle($peer, $phone);
            }
        );
    }

    $runLoop = new ReflectionMethod($trunk, 'runAudioTransmissionLoop');
    Swoole\Coroutine\run(static function () use ($runLoop, $trunk): void {
        $runLoop->invoke($trunk);
    });

    pacingAssertSame($packetCount, count($socket->packets), "{$codec}/{$ptimeMs}: quantidade de RTPs");
    pacingAssertSame($packetCount, count($socket->sendTimesNs), "{$codec}/{$ptimeMs}: quantidade de timestamps reais");

    $deltasMs = [];
    for ($i = 1; $i < count($socket->sendTimesNs); $i++) {
        $deltasMs[] = ($socket->sendTimesNs[$i] - $socket->sendTimesNs[$i - 1]) / 1_000_000;
    }
    $meanMs = array_sum($deltasMs) / count($deltasMs);
    $elapsedMs = ($socket->sendTimesNs[array_key_last($socket->sendTimesNs)] - $socket->sendTimesNs[0]) / 1_000_000;
    $expectedElapsedMs = ($packetCount - 1) * $ptimeMs;
    $meanToleranceMs = max(1.5, $ptimeMs * 0.08);

    $decoded = array_map('pacingDecodeRtp', $socket->packets);
    $expectedTimestampDelta = (int)round($frequency * ($ptimeMs / 1000));
    if ($validateStaticPtime) {
        pacingAssert(
            abs($meanMs - $ptimeMs) <= $meanToleranceMs,
            sprintf('%s/%d: pacing médio %.3f ms fora da tolerância de %.3f ms', $codec, $ptimeMs, $meanMs, $meanToleranceMs)
        );
        pacingAssert(
            abs($elapsedMs - $expectedElapsedMs) <= $meanToleranceMs * ($packetCount - 1),
            sprintf('%s/%d: drift acumulado excessivo (%.3f ms)', $codec, $ptimeMs, $elapsedMs - $expectedElapsedMs)
        );

        for ($i = 1; $i < count($decoded); $i++) {
            pacingAssertSame(
                1,
                ($decoded[$i]['sequence'] - $decoded[$i - 1]['sequence']) & 0xffff,
                "{$codec}/{$ptimeMs}: sequence incrementa uma vez"
            );
            pacingAssertSame(
                $expectedTimestampDelta,
                ($decoded[$i]['timestamp'] - $decoded[$i - 1]['timestamp']) & 0xffffffff,
                "{$codec}/{$ptimeMs}: timestamp delta"
            );
        }
    }

    $payloadBytes = strlen($decoded[0]['payload']);
    if ($validateStaticPtime) {
        if ($codec === 'PCMA') {
            pacingAssertSame($expectedTimestampDelta, $payloadBytes, "PCMA/{$ptimeMs}: payload possui um byte por sample");
        } elseif ($codec === 'G729') {
            pacingAssertSame(intdiv($ptimeMs, 10) * 10, $payloadBytes, "G729/{$ptimeMs}: payload possui 10 bytes por frame de 10 ms");
        } else {
            pacingAssert($payloadBytes > 0, "OPUS/{$ptimeMs}: payload não pode ser vazio");
        }
    }

    return [
        'codec' => $codec,
        'ptime_ms' => $ptimeMs,
        'processing_ms' => $processingMs,
        'mean_ms' => $meanMs,
        'min_ms' => min($deltasMs),
        'max_ms' => max($deltasMs),
        'elapsed_ms' => $elapsedMs,
        'expected_elapsed_ms' => $expectedElapsedMs,
        'payload_bytes' => $payloadBytes,
        'timestamp_delta' => $expectedTimestampDelta,
        'packets' => $socket->packets,
        'times' => $socket->sendTimesNs,
    ];
}

$wavPath = tempnam(sys_get_temp_dir(), 'libspech-pacing-');
if ($wavPath === false) {
    throw new RuntimeException('Não foi possível criar WAV temporário');
}
$pcm = str_repeat("\x00\x00", 8000 * 3);
file_put_contents($wavPath, \libspech\Sip\waveHead3(strlen($pcm), 8000, 1) . $pcm);

try {
    $results = [
        pacingRunCodec($wavPath, 'PCMA', 20, 36),
        pacingRunCodec($wavPath, 'PCMA', 10, 50),
        pacingRunCodec($wavPath, 'PCMA', 40, 24),
        pacingRunCodec($wavPath, 'G729', 20, 36),
        pacingRunCodec($wavPath, 'PCMA', 20, 100, 3),
    ];

    if (class_exists('opusChannel')) {
        $results[] = pacingRunCodec($wavPath, 'OPUS', 20, 30);
    }

    // Mudança durante um sendto(): o pacote atual ainda é de 10 ms; o próximo
    // ciclo já deve usar payload/timestamp/cadência de 40 ms, sem catch-up.
    $dynamic = pacingRunCodec(
        $wavPath,
        'PCMA',
        10,
        9,
        0,
        static fn(trunkController $trunk): Closure => static function (int $packetNumber) use ($trunk): void {
            if ($packetNumber === 4) {
                $trunk->setPacketTime(40);
            }
        },
        false
    );
    $dynamicDecoded = array_map('pacingDecodeRtp', $dynamic['packets']);
    pacingAssertSame(80, strlen($dynamicDecoded[3]['payload']), 'ptime dinâmico: pacote da transição ainda usa 10 ms');
    pacingAssertSame(320, strlen($dynamicDecoded[4]['payload']), 'ptime dinâmico: próximo pacote usa 40 ms');
    pacingAssertSame(80, $dynamicDecoded[4]['timestamp'] - $dynamicDecoded[3]['timestamp'], 'ptime dinâmico: timeline preserva frame anterior');
    pacingAssertSame(320, $dynamicDecoded[5]['timestamp'] - $dynamicDecoded[4]['timestamp'], 'ptime dinâmico: timestamp seguinte usa 40 ms');
    $transitionDeltaMs = ($dynamic['times'][4] - $dynamic['times'][3]) / 1_000_000;
    pacingAssert(abs($transitionDeltaMs - 40) <= 4, sprintf('ptime dinâmico: intervalo de transição %.3f ms', $transitionDeltaMs));

    // Um atraso maior que o próprio ptime não pode deixar vários deadlines em
    // aberto. Após o frame atrasado, a cadência deve voltar a 20 ms sem rajada.
    $overrun = pacingRunCodec(
        $wavPath,
        'PCMA',
        20,
        9,
        0,
        static fn(): Closure => static function (int $packetNumber): void {
            if ($packetNumber === 4) {
                Swoole\Coroutine::sleep(0.055);
            }
        },
        false
    );
    $overrunGapMs = ($overrun['times'][4] - $overrun['times'][3]) / 1_000_000;
    $afterOverrunGapMs = ($overrun['times'][5] - $overrun['times'][4]) / 1_000_000;
    pacingAssert($overrunGapMs >= 50, sprintf('overrun: atraso artificial não foi observado (%.3f ms)', $overrunGapMs));
    pacingAssert(
        $afterOverrunGapMs >= 15 && $afterOverrunGapMs <= 25,
        sprintf('overrun: pacer tentou recuperar com rajada (próximo delta %.3f ms)', $afterOverrunGapMs)
    );

    // O evento usa timestamp fixo, avança a timeline uma única vez ao final e
    // o primeiro áudio posterior começa exatamente após os 160 ms do DTMF.
    $dtmfMedia = pacingMediaWithoutConstructor();
    $dtmfSocket = new TrunkPacingCaptureSocket();
    $dtmfMedia->socket = $dtmfSocket;
    $dtmfMedia->setPacketTime(20);
    $dtmfRtp = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, 20, 0x55667788);
    $dtmfRtp->sequenceNumber = 2000;
    $dtmfRtp->timestamp = 9000;
    $dtmfMedia->members['dtmf'] = [
        'address' => '127.0.0.1',
        'port' => 33000,
        'frequency' => 8000,
        'rtpChannel' => $dtmfRtp,
    ];
    Swoole\Coroutine\run(static function () use ($dtmfMedia): void {
        $dtmfMedia->send2833('5');
    });
    pacingAssertSame(10280, $dtmfRtp->timestamp, 'DTMF: timeline avança 160 ms ao terminar');
    $sequenceAfterDtmf = $dtmfRtp->sequenceNumber;
    $audioAfterDtmf = pacingDecodeRtp($dtmfRtp->buildAudioPacket(str_repeat("\xD5", 160)));
    pacingAssertSame(10280, $audioAfterDtmf['timestamp'], 'DTMF: áudio retorna no timestamp seguinte ao evento');
    pacingAssertSame($sequenceAfterDtmf, $audioAfterDtmf['sequence'], 'DTMF: áudio preserva sequence contínua');
    pacingAssertSame(10440, $dtmfRtp->timestamp, 'DTMF: áudio posterior volta a avançar um ptime');

    foreach ($results as $result) {
        printf(
            "%s %d ms (custo %d ms): média %.3f ms, min %.3f, max %.3f, drift %+.3f ms, payload %d B, ts +%d\n",
            $result['codec'],
            $result['ptime_ms'],
            $result['processing_ms'],
            $result['mean_ms'],
            $result['min_ms'],
            $result['max_ms'],
            $result['elapsed_ms'] - $result['expected_elapsed_ms'],
            $result['payload_bytes'],
            $result['timestamp_delta']
        );
    }
    printf("PCMA ptime dinâmico 10→40 ms: intervalo da transição %.3f ms\n", $transitionDeltaMs);
    printf("PCMA overrun 55 ms: atraso %.3f ms, próximo intervalo %.3f ms (sem rajada)\n", $overrunGapMs, $afterOverrunGapMs);
    echo "DTMF 20 ms: timeline de áudio retomada em +1280 samples, depois +160.\n";
    echo "OK: pacing RTP monotônico validado no emissor do trunkController.\n";
} finally {
    unlink($wavPath);
}
