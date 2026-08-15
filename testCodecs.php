<?php

declare(strict_types=1);

ini_set('memory_limit', '512M');

\Swoole\Runtime::enableCoroutine();

include 'plugins/autoloader.php';

function generateSinePcm(
    int $sampleRate,
    int $ptimeMs,
    float $frequencyHz = 440.0,
    float $amplitude = 0.50
): string {
    $samples = (int) round($sampleRate * ($ptimeMs / 1000));

    $pcm = '';

    for ($i = 0; $i < $samples; $i++) {
        $value = sin(
            2.0 * M_PI * $frequencyHz * ($i / $sampleRate)
        );

        $sample = (int) round(
            $value * 32767 * $amplitude
        );

        if ($sample < 0) {
            $sample += 0x10000;
        }

        $pcm .= pack('v', $sample);
    }

    return $pcm;
}

function pcm16leToSamples(string $pcm): array
{
    $samples = [];
    $length = strlen($pcm);

    for ($i = 0; $i + 1 < $length; $i += 2) {
        $value = unpack('v', substr($pcm, $i, 2))[1];

        if ($value >= 0x8000) {
            $value -= 0x10000;
        }

        $samples[] = $value;
    }

    return $samples;
}

function calculateMeanAbsoluteError(
    string $original,
    string $decoded
): float {
    $a = pcm16leToSamples($original);
    $b = pcm16leToSamples($decoded);

    $count = min(
        count($a),
        count($b)
    );

    if ($count === 0) {
        return 0.0;
    }

    $sum = 0.0;

    for ($i = 0; $i < $count; $i++) {
        $sum += abs(
            $a[$i] - $b[$i]
        );
    }

    return $sum / $count;
}

function calculatePeakError(
    string $original,
    string $decoded
): int {
    $a = pcm16leToSamples($original);
    $b = pcm16leToSamples($decoded);

    $count = min(
        count($a),
        count($b)
    );

    $peak = 0;

    for ($i = 0; $i < $count; $i++) {
        $error = abs(
            $a[$i] - $b[$i]
        );

        if ($error > $peak) {
            $peak = $error;
        }
    }

    return $peak;
}

function countDifferentBytes(
    string $a,
    string $b
): int {
    $length = min(
        strlen($a),
        strlen($b)
    );

    $different = 0;

    for ($i = 0; $i < $length; $i++) {
        if ($a[$i] !== $b[$i]) {
            $different++;
        }
    }

    $different += abs(
        strlen($a) - strlen($b)
    );

    return $different;
}

function testPcmaPtime(
    int $ptimeMs
): array {
    $sampleRate = 8000;

    $expectedSamples = (int) round(
        $sampleRate * ($ptimeMs / 1000)
    );

    $expectedPcmBytes =
        $expectedSamples * 2;

    $expectedPcmaBytes =
        $expectedSamples;

    /*
     * PCM original
     */
    $pcm = generateSinePcm(
        sampleRate: $sampleRate,
        ptimeMs: $ptimeMs,
        frequencyHz: 440.0,
        amplitude: 0.50
    );

    /*
     * PCM -> PCMA
     */
    $encoded = encodePcmToPcma(
        $pcm
    );

    /*
     * PCMA -> PCM
     */
    $decoded = decodePcmaToPcm(
        $encoded
    );

    /*
     * PCM decodificado -> PCMA novamente
     */
    $reencoded = encodePcmToPcma(
        $decoded
    );

    /*
     * Opcional:
     * PCMA #2 -> PCM novamente
     */
    $decodedAgain = decodePcmaToPcm(
        $reencoded
    );

    $pcmBytes =
        strlen($pcm);

    $pcmaBytes =
        strlen($encoded);

    $decodedBytes =
        strlen($decoded);

    $reencodedBytes =
        strlen($reencoded);

    $decodedAgainBytes =
        strlen($decodedAgain);

    $sizeValid =
        $pcmBytes === $expectedPcmBytes
        &&
        $pcmaBytes === $expectedPcmaBytes
        &&
        $decodedBytes === $expectedPcmBytes
        &&
        $reencodedBytes === $expectedPcmaBytes
        &&
        $decodedAgainBytes === $expectedPcmBytes;

    /*
     * Essa é uma validação interessante:
     *
     * Depois que o PCM já foi quantizado para A-law,
     * decodificar e encodar novamente normalmente deve
     * produzir exatamente o mesmo PCMA.
     */
    $pcmaStable =
        $encoded === $reencoded;

    $differentPcmaBytes =
        countDifferentBytes(
            $encoded,
            $reencoded
        );

    $maeFirstDecode =
        calculateMeanAbsoluteError(
            $pcm,
            $decoded
        );

    $peakFirstDecode =
        calculatePeakError(
            $pcm,
            $decoded
        );

    /*
     * Se PCMA #1 === PCMA #2,
     * esses dois PCMs também devem ser iguais.
     */
    $decodedStable =
        $decoded === $decodedAgain;

    return [
        'ptime_ms' =>
            $ptimeMs,

        'expected_samples' =>
            $expectedSamples,

        'pcm_bytes' =>
            $pcmBytes,

        'pcma1_bytes' =>
            $pcmaBytes,

        'decoded1_bytes' =>
            $decodedBytes,

        'pcma2_bytes' =>
            $reencodedBytes,

        'decoded2_bytes' =>
            $decodedAgainBytes,

        'size_valid' =>
            $sizeValid,

        'pcma_stable' =>
            $pcmaStable,

        'pcma_different_bytes' =>
            $differentPcmaBytes,

        'decoded_stable' =>
            $decodedStable,

        'mean_absolute_error' =>
            $maeFirstDecode,

        'peak_error' =>
            $peakFirstDecode,

        'valid' =>
            $sizeValid
            &&
            $pcmaStable
            &&
            $decodedStable,
    ];
}

\Swoole\Coroutine\run(
    function (): void {
        $ptimes = [
            5,
            10,
            15,
            20,
            30,
            40,
            50,
            60,
        ];

        echo PHP_EOL;
        echo "PCMA variable ptime encode/decode/re-encode test";
        echo PHP_EOL;

        echo str_repeat(
            '=',
            150
        );

        echo PHP_EOL;

        foreach ($ptimes as $ptime) {
            try {
                $result =
                    testPcmaPtime(
                        $ptime
                    );

                printf(
                    "ptime=%3d ms | samples=%4d | PCM=%4d | PCMA1=%4d | PCM1=%4d | PCMA2=%4d | PCM2=%4d | diffPCMA=%3d | PCMA stable=%s | PCM stable=%s | MAE=%8.2f | peak=%5d | %s\n",
                    $result['ptime_ms'],
                    $result['expected_samples'],
                    $result['pcm_bytes'],
                    $result['pcma1_bytes'],
                    $result['decoded1_bytes'],
                    $result['pcma2_bytes'],
                    $result['decoded2_bytes'],
                    $result['pcma_different_bytes'],
                    $result['pcma_stable']
                        ? 'YES'
                        : 'NO',
                    $result['decoded_stable']
                        ? 'YES'
                        : 'NO',
                    $result['mean_absolute_error'],
                    $result['peak_error'],
                    $result['valid']
                        ? 'OK'
                        : 'FAIL'
                );

            } catch (\Throwable $e) {
                printf(
                    "ptime=%3d ms | EXCEPTION: %s\n",
                    $ptime,
                    $e->getMessage()
                );
            }
        }

        echo str_repeat(
            '=',
            150
        );

        echo PHP_EOL;
    }
);