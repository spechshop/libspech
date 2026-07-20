<?php

namespace libspech\Sip;

use Exception;
use Swoole\Coroutine;

function secureAudioVoip(string $filename, bool $forceMono = true): bool
{
    $raw = file_get_contents($filename);
    if (!$raw) {
        throw new Exception("Não foi possível ler o arquivo: $filename");
    }

    // -----------------------------
    // 1) HEADER RIFF/WAVE
    // -----------------------------
    if (substr($raw, 0, 4) !== "RIFF" || substr($raw, 8, 4) !== "WAVE") {
        return false; // ignora
    }

    $len = strlen($raw);
    $pos = 12;

    $fmtChunk = null;
    $dataOffset = null;
    $dataSize = null;

    // -----------------------------
    // 2) PERCORRER CHUNKS
    // -----------------------------
    while ($pos + 8 <= $len) {
        $id = substr($raw, $pos, 4);
        $size = unpack("V", substr($raw, $pos + 4, 4))[1];
        $chunkData = $pos + 8;

        if ($id === "fmt ") {
            $fmtChunk = substr($raw, $chunkData, $size);
        } elseif ($id === "data") {
            $dataOffset = $chunkData;
            $dataSize = $size;
            break;
        }

        $pos += 8 + $size;
        if ($pos % 2 === 1) $pos++;
    }

    if (!$fmtChunk || !$dataOffset) {
        return false;
    }

    // -----------------------------
    // 3) Ler info do fmt
    // -----------------------------
    $audioFormat = unpack("v", substr($fmtChunk, 0, 2))[1];
    $channels = unpack("v", substr($fmtChunk, 2, 2))[1];
    $sampleRate = unpack("V", substr($fmtChunk, 4, 4))[1];
    $bits = unpack("v", substr($fmtChunk, 14, 2))[1];

    // -----------------------------
    // 4) Decisão inteligente
    // -----------------------------

    // Arquivo já está no formato correto → NÃO faz nada
    if (
        $audioFormat === 1 &&   // PCM
        $bits == 16 &&          // 16-bit
        $channels == 1 &&       // mono
        $sampleRate == 48000    // 48kHz
    ) {
        return false;
    }

    // Normal WAV sem chunks extras → não é "arquivo chato"
    if ($dataOffset === 44) {
        return false;
    }

    // Apenas converte se for dos "chatos"
    $isBad =
        ($audioFormat == 1) &&
        ($bits == 24) &&
        ($channels == 2) &&
        ($sampleRate == 44100) &&
        ($dataOffset > 44);

    if (!$isBad) {
        return false;
    }

    // --------------------------------------------------
    // COMEÇA A CONVERSÃO (“arquivo chato” detectado)
    // --------------------------------------------------

    $pcm = substr($raw, $dataOffset, $dataSize);
    $frameSize = 3 * $channels;
    $totalFrames = intdiv(strlen($pcm), $frameSize);

    // Helper 24-bit → float32
    $read24 = function (string $s, int $offset): float {
        $b0 = ord($s[$offset]);
        $b1 = ord($s[$offset + 1]);
        $b2 = ord($s[$offset + 2]);
        $u = $b0 | ($b1 << 8) | ($b2 << 16);
        if ($u & 0x800000) $u -= 0x1000000;
        return $u / 8388608.0;
    };

    // -----------------------------
    // 5) MONO MIX
    // -----------------------------
    $mono = [];
    $peak = 0.0;

    for ($i = 0; $i < $totalFrames; $i++) {
        $off = $i * $frameSize;
        $l = $read24($pcm, $off);
        $r = ($channels > 1) ? $read24($pcm, $off + 3) : $l;

        $s = $forceMono ? (($l + $r) * 0.5) : $l;

        $peak = max($peak, abs($s));
        $mono[] = $s;
    }

    // -----------------------------
    // 6) NORMALIZAÇÃO (prevent clipping)
    // -----------------------------
    $targetPeak = 0.98;
    $gain = ($peak > $targetPeak) ? ($targetPeak / $peak) : 1.0;

    foreach ($mono as &$f) $f *= $gain;
    unset($f);

    // -----------------------------
    // 7) RESAMPLE 44.1k → 48k
    // -----------------------------
    $rateIn = 44100;
    $rateOut = 48000;

    $ratio = $rateIn / $rateOut;
    $outFrames = (int)floor(count($mono) * ($rateOut / $rateIn));
    $out = [];

    for ($n = 0; $n < $outFrames; $n++) {
        $src = $n * $ratio;
        $i0 = (int)$src;
        $i1 = min($i0 + 1, count($mono) - 1);
        $frac = $src - $i0;
        $out[] = $mono[$i0] + ($mono[$i1] - $mono[$i0]) * $frac;
    }

    // -----------------------------
    // 8) FLOAT → INT16
    // -----------------------------
    $pcmOut = "";
    foreach ($out as $f) {
        if ($f > 1.0) $f = 1.0;
        if ($f < -1.0) $f = -1.0;
        $val = (int)round($f * 32767);
        $pcmOut .= pack("v", $val & 0xFFFF);
    }

    // -----------------------------
    // 9) HEADER WAV 16-bit mono 48kHz
    // -----------------------------
    $byteRate = $rateOut * 2;
    $blockAlign = 2;

    $header =
        pack("A4V", "RIFF", 36 + strlen($pcmOut)) .
        "WAVE" .
        pack("A4VvvVVvv",
            "fmt ", 16, 1, 1,
            $rateOut,
            $byteRate,
            $blockAlign,
            16
        ) .
        pack("A4V", "data", strlen($pcmOut));

    // -----------------------------
    // 10) Sobrescrever o original
    // -----------------------------
    file_put_contents($filename, $header . $pcmOut);

    return true;
}

function wavChunks(string $file)
{
    $raw = file_get_contents($file);
    if (!$raw) {
        die("Erro ao ler arquivo.\n");
    }

    if (substr($raw, 0, 4) !== "RIFF" || substr($raw, 8, 4) !== "WAVE") {
        die("Não é WAV válido.\n");
    }


    $len = strlen($raw);

    // Começa após "RIFF + size + WAVE"
    $pos = 12;

    $chunks = [];

    while ($pos + 8 <= $len) {

        $id = substr($raw, $pos, 4);
        $size = unpack("V", substr($raw, $pos + 4, 4))[1];

        $start = $pos;
        $dataStart = $pos + 8;
        $dataEnd = $dataStart + $size;


        if ($dataEnd > $len) {

        }

        $chunks[] = [
            'id' => $id,
            'start' => $start,
            'size' => $size,
            'data' => $dataStart,
            'end' => $dataEnd,
        ];

        // pula chunk
        $pos = $dataEnd;

        // alinhamento para byte par
        if ($pos % 2 === 1) {
            $pos++;

        }

        if ($pos >= $len) break;
    }



    return $chunks;
}


function getInfoAudio(string $filename): array
{
    $raw = file_get_contents($filename);
    $infoMedia = substr(file_get_contents($filename), 0, 44);
    $sampleRate = unpack("V", substr($infoMedia, 24, 4))[1];
    $numChannels = unpack("v", substr($infoMedia, 22, 2))[1];
    $bitDepth = unpack("v", substr($infoMedia, 34, 2))[1];
    return [
        'rate' => $sampleRate,
        'numChannels' => $numChannels,
        'bitDepth' => $bitDepth
    ];
}

/**
 * Calcula o tamanho do chunk PCM para um determinado sample rate e duração
 *
 * @param int $sampleRate Taxa de amostragem (Hz)
 * @param int $channels Número de canais (1=mono, 2=stereo)
 * @param int $bitsPerSample Bits por sample (8, 16, 24, 32)
 * @param float $durationMs Duração em milissegundos (padrão: 20ms)
 * @return int Tamanho do chunk em bytes
 *
 * Exemplos:
 * - 8kHz, mono, 16-bit, 20ms = 320 bytes
 * - 16kHz, mono, 16-bit, 20ms = 640 bytes
 * - 48kHz, mono, 16-bit, 20ms = 1920 bytes
 */
function calculateChunkSize(int $sampleRate, int $channels = 1, int $bitsPerSample = 16, float $durationMs = 20.0): int
{
    $bytesPerSample = $bitsPerSample / 8;
    $samplesNeeded = (int)($sampleRate * ($durationMs / 1000.0));
    return (int)($samplesNeeded * $channels * $bytesPerSample);
}

/**
 * Normaliza o volume de um buffer PCM 16-bit little-endian
 * Útil após resampling para evitar clipping/distorção
 *
 * @param string $pcmData Buffer PCM 16-bit LE
 * @param float $targetPeak Pico alvo (0.0 a 1.0, padrão 0.85 = -1.4dB)
 * @return string PCM normalizado
 */
function normalizePcm(string $pcmData, float $targetPeak = 0.85): string
{
    if (strlen($pcmData) < 2) {
        return $pcmData;
    }

    // Encontrar o pico
    $maxSample = 0;
    $len = strlen($pcmData);

    for ($i = 0; $i < $len; $i += 2) {
        $sample = unpack('s', substr($pcmData, $i, 2))[1];
        $maxSample = max($maxSample, abs($sample));
    }

    // Se não houver sinal ou já estiver normalizado, retorna
    if ($maxSample == 0 || $maxSample <= 32767 * $targetPeak) {
        return $pcmData;
    }

    // Calcular ganho para normalizar
    $gain = (32767 * $targetPeak) / $maxSample;

    // Aplicar ganho
    $normalized = '';
    for ($i = 0; $i < $len; $i += 2) {
        $sample = unpack('s', substr($pcmData, $i, 2))[1];
        $newSample = (int)($sample * $gain);

        // Clamping para evitar overflow
        $newSample = max(-32768, min(32767, $newSample));
        $normalized .= pack('s', $newSample);
    }

    return $normalized;
}

/**
 * Aplica atenuação simples em um buffer PCM (reduz volume)
 *
 * @param string $pcmData Buffer PCM 16-bit LE
 * @param float $gain Ganho (0.0 a 1.0), ex: 0.5 = -6dB
 * @return string PCM atenuado
 */
function attenuatePcm(string $pcmData, float $gain = 0.7): string
{
    if (strlen($pcmData) < 2) {
        return $pcmData;
    }

    $len = strlen($pcmData);
    $attenuated = '';

    for ($i = 0; $i < $len; $i += 2) {
        $sample = unpack('s', substr($pcmData, $i, 2))[1];
        $newSample = (int)($sample * $gain);
        $attenuated .= pack('s', $newSample);
    }

    return $attenuated;
}

function secure_random_bytes(int $length): string
{
    try {
        return random_bytes($length);
    } catch (Exception $e) {
        $fs = '';
        for (; $length--;) $fs .= chr(mt_rand(0, 255));
        return $fs;
    }
}


function interruptibleSleep(float $seconds, &$abort, float $stepSeconds = 0.050): bool
{
    if ($seconds <= 0) {
        return !$abort;
    }

    $deadline = hrtime(true) + (int)round($seconds * 1_000_000_000);

    // Swoole não aceita sleep menor que 1ms
    $minSleepNs = 1_000_000; // 0.001s
    $stepNs = max($minSleepNs, (int)round($stepSeconds * 1_000_000_000));

    while (true) {
        if ($abort) {
            return false;
        }

        $remainingNs = $deadline - hrtime(true);

        if ($remainingNs <= 0) {
            return true;
        }

        // Se falta menos que 1ms, não chama sleep.
        // Cede a execução por 1ms no máximo só se ainda fizer sentido.
        if ($remainingNs < $minSleepNs) {
            return true;
        }

        $sleepNs = min($stepNs, $remainingNs);

        Coroutine::sleep($sleepNs / 1_000_000_000);
    }
}


function randf(int|float $min, int|float $max): float
{
    if (is_float($min) || is_float($max)) {
        if ($min > $max) {
            return new \Random\Randomizer()->getFloat($max, $min);
        }
        return new \Random\Randomizer()->getFloat($min, $max);
    }
    return rand(
        $min,
        $max
    );
}

/**
 * Converte áudio mono para stereo (duplicando o canal)
 *
 * @param string $pcmData Buffer PCM 16-bit mono LE
 * @return string PCM 16-bit stereo LE
 */
function monoToStereo(string $pcmData): string
{
    if (strlen($pcmData) < 2) {
        return $pcmData;
    }

    $len = strlen($pcmData);
    $stereo = '';

    for ($i = 0; $i < $len; $i += 2) {
        $sample = substr($pcmData, $i, 2);
        $stereo .= $sample . $sample; // L e R iguais
    }

    return $stereo;
}

/**
 * Converte áudio stereo para mono (média dos canais L e R)
 *
 * @param string $pcmData Buffer PCM 16-bit stereo LE
 * @return string PCM 16-bit mono LE
 */
function stereoToMono(string $pcmData): string
{
    if (strlen($pcmData) < 4) {
        return $pcmData;
    }

    $len = strlen($pcmData);
    $mono = '';

    for ($i = 0; $i < $len; $i += 4) {
        $left = unpack('s', substr($pcmData, $i, 2))[1];
        $right = unpack('s', substr($pcmData, $i + 2, 2))[1];

        $avg = (int)(($left + $right) / 2);
        $mono .= pack('s', $avg);
    }

    return $mono;
}

/**
 * Calcula a frequência dominante aproximada de um frame de áudio PCM 16-bit LE
 * usando o método de taxa de cruzamento por zero (Zero-Crossing Rate)
 *
 * @param string $pcmData Buffer PCM 16-bit mono LE
 * @param int $sampleRate Taxa de amostragem em Hz (padrão: 8000)
 * @return float Frequência estimada em Hz (0.0 se não houver cruzamentos ou dados insuficientes)
 *
 * Nota: ZCR é uma aproximação simples adequada para sinais periódicos.
 * Para análise de frequência precisa, considere FFT.
 *
 * Exemplos:
 * - Tom puro 440Hz a 8kHz: retorna ~440Hz
 * - Tom puro 1000Hz a 16kHz: retorna ~1000Hz
 * - Silêncio ou ruído: retorna valores baixos ou imprecisos
 */
function calculatePcmFrequency(string $pcmData, int $sampleRate = 8000): float
{
    $len = strlen($pcmData);

    if ($len < 4) {
        return 0.0;
    }

    // Contar cruzamentos por zero
    $zeroCrossings = 0;
    $prevSample = unpack('s', substr($pcmData, 0, 2))[1];

    for ($i = 2; $i < $len; $i += 2) {
        $sample = unpack('s', substr($pcmData, $i, 2))[1];

        // Detecta mudança de sinal (cruzamento por zero)
        if (($prevSample >= 0 && $sample < 0) || ($prevSample < 0 && $sample >= 0)) {
            $zeroCrossings++;
        }

        $prevSample = $sample;
    }

    // Número total de samples
    $numSamples = $len / 2;

    if ($numSamples == 0) {
        return 0.0;
    }

    // Frequência = (cruzamentos / 2) / duração
    // Duração = numSamples / sampleRate
    // Frequência = (cruzamentos / 2) * sampleRate / numSamples
    $frequency = ($zeroCrossings / 2.0) * ($sampleRate / $numSamples);

    return $frequency;
}



/**
 * Verifica se um buffer PCM16LE mono contém o padrão de ringback
 * de aproximadamente 425 Hz repetido a cada 5 segundos.
 *
 * @return array{
 *     has_ring_pattern: bool,
 *     ring_from_start_to_end: bool,
 *     reason: string,
 *     confidence: float,
 *     duration_ms: int,
 *     pulses: array,
 *     matched_pulses: array,
 *     periods_ms: array,
 *     disturbance_at_ms: ?int,
 *     disturbance_duration_ms: int,
 *     frames: array
 * }
 */
function analyzeRingPcm(
    string $pcmData,
    int    $sampleRate = 8000,
    int    $frameDurationMs = 500,
    float  $ringFrequencyHz = 425.0,
    int    $expectedPeriodMs = 5000,
    int    $periodToleranceMs = 600,
    int    $minimumDisturbanceMs = 300
): array
{
    if ($sampleRate <= 0) {
        throw new InvalidArgumentException(
            'sampleRate deve ser maior que zero.'
        );
    }

    if ($frameDurationMs <= 0) {
        throw new InvalidArgumentException(
            'frameDurationMs deve ser maior que zero.'
        );
    }

    if ($pcmData === '') {
        return [
            'has_ring_pattern' => false,
            'ring_from_start_to_end' => false,
            'reason' => 'pcm_vazio',
            'confidence' => 0.0,
            'duration_ms' => 0,
            'pulses' => [],
            'matched_pulses' => [],
            'periods_ms' => [],
            'disturbance_at_ms' => null,
            'disturbance_duration_ms' => 0,
            'frames' => [],
        ];
    }

    if ((strlen($pcmData) % 2) !== 0) {
        throw new InvalidArgumentException(
            'O PCM16 precisa possuir quantidade par de bytes.'
        );
    }

    $samplesPerFrame = (int)round(
        $sampleRate * ($frameDurationMs / 1000)
    );

    $frameBytes = $samplesPerFrame * 2;

    if (strlen($pcmData) < $frameBytes) {
        throw new InvalidArgumentException(
            'O buffer PCM é muito curto para análise.'
        );
    }

    $decodePcm16Le = static function (string $pcm): array {
        $values = unpack('v*', $pcm);

        if ($values === false) {
            return [];
        }

        $samples = [];

        foreach ($values as $value) {
            if ($value >= 0x8000) {
                $value -= 0x10000;
            }

            $samples[] = (float)$value;
        }

        return $samples;
    };

    $calculateRmsDbfs = static function (array $samples): float {
        if ($samples === []) {
            return -120.0;
        }

        $sumSquares = 0.0;

        foreach ($samples as $sample) {
            $sumSquares += $sample * $sample;
        }

        $rms = sqrt(
            $sumSquares / count($samples)
        );

        if ($rms <= 0.0) {
            return -120.0;
        }

        return max(
            -120.0,
            20.0 * log10($rms / 32768.0)
        );
    };

    $goertzelDbfs = static function (
        array $samples,
        int   $rate,
        float $frequency
    ): float {
        $sampleCount = count($samples);

        if ($sampleCount <= 1) {
            return -120.0;
        }

        $omega =
            (2.0 * M_PI * $frequency) /
            $rate;

        $coefficient = 2.0 * cos($omega);

        $previous = 0.0;
        $previousPrevious = 0.0;
        $divisor = $sampleCount - 1;

        foreach ($samples as $index => $sample) {
            $hann = 0.5 * (
                    1.0 -
                    cos(
                        (2.0 * M_PI * $index) /
                        $divisor
                    )
                );

            $windowedSample = $sample * $hann;

            $current =
                $windowedSample +
                ($coefficient * $previous) -
                $previousPrevious;

            $previousPrevious = $previous;
            $previous = $current;
        }

        $power =
            ($previous * $previous) +
            ($previousPrevious * $previousPrevious) -
            ($coefficient * $previous * $previousPrevious);

        if ($power <= 0.0) {
            return -120.0;
        }

        $amplitude =
            (4.0 * sqrt($power)) /
            $sampleCount;

        if ($amplitude <= 0.0) {
            return -120.0;
        }

        return max(
            -120.0,
            min(
                0.0,
                20.0 * log10(
                    $amplitude / 32768.0
                )
            )
        );
    };

    $median = static function (array $values): float {
        if ($values === []) {
            return -120.0;
        }

        sort($values, SORT_NUMERIC);

        $count = count($values);
        $middle = intdiv($count, 2);

        if (($count % 2) === 1) {
            return (float)$values[$middle];
        }

        return (
                $values[$middle - 1] +
                $values[$middle]
            ) / 2.0;
    };

    $backgroundFrequencies = [
        250.0,
        300.0,
        350.0,
        500.0,
        600.0,
        700.0,
        850.0,
        1000.0,
        1200.0,
        1500.0,
    ];

    /*
     * O tom nominal e 425 Hz, mas centrais e gateways podem entrega-lo
     * deslocado. A faixa abaixo cobre de 395 a 460 Hz para o valor padrao,
     * sem precisar reduzir os limiares de energia e proeminencia.
     */
    $ringCandidates = [];

    for (
        $candidateFrequency = $ringFrequencyHz - 30.0;
        $candidateFrequency <= $ringFrequencyHz + 35.0;
        $candidateFrequency += 5.0
    ) {
        $ringCandidates[] = $candidateFrequency;
    }

    $minimumRingLevelDbfs = -48.0;
    $minimumRingProminenceDb = 10.0;
    $silenceThresholdDbfs = -50.0;

    $frames = [];
    $pcmLength = strlen($pcmData);
    $frameIndex = 0;

    for (
        $offset = 0;
        ($offset + $frameBytes) <= $pcmLength;
        $offset += $frameBytes
    ) {
        $framePcm = substr(
            $pcmData,
            $offset,
            $frameBytes
        );

        $samples = $decodePcm16Le($framePcm);

        if ($samples === []) {
            continue;
        }

        $startMs =
            $frameIndex *
            $frameDurationMs;

        $rmsDbfs = $calculateRmsDbfs($samples);

        $bestRingFrequency = $ringFrequencyHz;
        $bestRingLevelDbfs = -120.0;

        foreach ($ringCandidates as $candidateFrequency) {
            $levelDbfs = $goertzelDbfs(
                $samples,
                $sampleRate,
                $candidateFrequency
            );

            if ($levelDbfs > $bestRingLevelDbfs) {
                $bestRingLevelDbfs = $levelDbfs;
                $bestRingFrequency = $candidateFrequency;
            }
        }

        $backgroundLevels = [];

        foreach ($backgroundFrequencies as $frequency) {
            $backgroundLevels[] = $goertzelDbfs(
                $samples,
                $sampleRate,
                $frequency
            );
        }

        $backgroundDbfs = $median($backgroundLevels);

        $prominenceDb =
            $bestRingLevelDbfs -
            $backgroundDbfs;

        $isRingTone =
            $bestRingLevelDbfs >=
            $minimumRingLevelDbfs &&
            $prominenceDb >=
            $minimumRingProminenceDb;

        $isSilence =
            !$isRingTone &&
            $rmsDbfs <=
            $silenceThresholdDbfs;

        $state = match (true) {
            $isRingTone => 'ring',
            $isSilence => 'silence',
            default => 'other',
        };

        $frames[] = [
            'index' => $frameIndex,
            'start_ms' => $startMs,
            'end_ms' => $startMs + $frameDurationMs,
            'state' => $state,
            'rms_dbfs' => round($rmsDbfs, 2),
            'ring_frequency_hz' => $bestRingFrequency,
            'ring_level_dbfs' => round(
                $bestRingLevelDbfs,
                2
            ),
            'prominence_db' => round(
                $prominenceDb,
                2
            ),
        ];

        $frameIndex++;
    }

    $durationMs =
        count($frames) *
        $frameDurationMs;

    /*
     * Agrupa frames próximos de 425 Hz em pulsos.
     */
    $pulses = [];
    $maximumInternalGapMs = 300;

    foreach ($frames as $frame) {
        if ($frame['state'] !== 'ring') {
            continue;
        }

        if ($pulses === []) {
            $pulses[] = [
                'start_ms' => $frame['start_ms'],
                'end_ms' => $frame['end_ms'],
                'duration_ms' => $frameDurationMs,
                'tone_frames' => 1,
            ];

            continue;
        }

        $lastIndex = count($pulses) - 1;

        $gapMs =
            $frame['start_ms'] -
            $pulses[$lastIndex]['end_ms'];

        if ($gapMs <= $maximumInternalGapMs) {
            $pulses[$lastIndex]['end_ms'] =
                $frame['end_ms'];

            $pulses[$lastIndex]['duration_ms'] =
                $frame['end_ms'] -
                $pulses[$lastIndex]['start_ms'];

            $pulses[$lastIndex]['tone_frames']++;

            continue;
        }

        $pulses[] = [
            'start_ms' => $frame['start_ms'],
            'end_ms' => $frame['end_ms'],
            'duration_ms' => $frameDurationMs,
            'tone_frames' => 1,
        ];
    }

    /*
     * Descarta detecções isoladas menores que 200 ms.
     */
    $pulses = array_values(
        array_filter(
            $pulses,
            static fn(array $pulse): bool => $pulse['tone_frames'] >= 2
        )
    );

    /*
     * Procura a maior sequência de pulsos separados por
     * aproximadamente cinco segundos.
     */
    $pulseCount = count($pulses);

    $chainLength = array_fill(
        0,
        $pulseCount,
        1
    );

    $previousPulse = array_fill(
        0,
        $pulseCount,
        null
    );

    $bestChainEnd = null;
    $bestChainLength = 0;

    for ($current = 0; $current < $pulseCount; $current++) {
        for ($previous = 0; $previous < $current; $previous++) {
            $periodMs =
                $pulses[$current]['start_ms'] -
                $pulses[$previous]['start_ms'];

            if (
                abs(
                    $periodMs -
                    $expectedPeriodMs
                ) >
                $periodToleranceMs
            ) {
                continue;
            }

            if (
                $chainLength[$previous] + 1 >
                $chainLength[$current]
            ) {
                $chainLength[$current] =
                    $chainLength[$previous] + 1;

                $previousPulse[$current] =
                    $previous;
            }
        }

        if ($chainLength[$current] > $bestChainLength) {
            $bestChainLength =
                $chainLength[$current];

            $bestChainEnd = $current;
        }
    }

    $matchedIndexes = [];

    while ($bestChainEnd !== null) {
        $matchedIndexes[] = $bestChainEnd;
        $bestChainEnd = $previousPulse[$bestChainEnd];
    }

    $matchedIndexes = array_reverse(
        $matchedIndexes
    );

    $matchedPulses = [];

    foreach ($matchedIndexes as $index) {
        $matchedPulses[] = $pulses[$index];
    }

    $periodsMs = [];

    for (
        $index = 1;
        $index < count($matchedPulses);
        $index++
    ) {
        $periodsMs[] =
            $matchedPulses[$index]['start_ms'] -
            $matchedPulses[$index - 1]['start_ms'];
    }

    /*
     * É necessário ao menos:
     *
     * pulso 1
     * + aproximadamente 5 segundos
     * + pulso 2
     */
    $hasRingPattern =
        count($matchedPulses) >= 2;

    /*
     * Protege as bordas dos pulsos para que o início e o fim
     * do tom não sejam confundidos com voz.
     */
    $protectedIntervals = [];
    $pulseEdgeToleranceMs = 400;

    foreach ($matchedPulses as $pulse) {
        $protectedIntervals[] = [
            'start_ms' => max(
                0,
                $pulse['start_ms'] -
                $pulseEdgeToleranceMs
            ),
            'end_ms' =>
                $pulse['end_ms'] +
                $pulseEdgeToleranceMs,
        ];
    }

    $isProtected = static function (
        int $timeMs
    ) use ($protectedIntervals): bool {
        foreach ($protectedIntervals as $interval) {
            if (
                $timeMs >= $interval['start_ms'] &&
                $timeMs <= $interval['end_ms']
            ) {
                return true;
            }
        }

        return false;
    };

    /*
     * Procura voz, mensagem ou outro áudio fora do ring.
     */
    $disturbanceAtMs = null;
    $disturbanceDurationMs = 0;
    $currentDisturbanceStartMs = null;

    foreach ($frames as $frame) {
        $frameMiddleMs = (int)(
            ($frame['start_ms'] + $frame['end_ms']) /
            2
        );

        $isDisturbance =
            $frame['state'] === 'other' &&
            !$isProtected($frameMiddleMs);

        if ($isDisturbance) {
            if ($currentDisturbanceStartMs === null) {
                $currentDisturbanceStartMs =
                    $frame['start_ms'];
            }

            continue;
        }

        if ($currentDisturbanceStartMs === null) {
            continue;
        }

        $currentDurationMs =
            $frame['start_ms'] -
            $currentDisturbanceStartMs;

        if (
            $currentDurationMs >=
            $minimumDisturbanceMs
        ) {
            $disturbanceAtMs =
                $currentDisturbanceStartMs;

            $disturbanceDurationMs =
                $currentDurationMs;

            break;
        }

        $currentDisturbanceStartMs = null;
    }

    if (
        $disturbanceAtMs === null &&
        $currentDisturbanceStartMs !== null
    ) {
        $currentDurationMs =
            $durationMs -
            $currentDisturbanceStartMs;

        if (
            $currentDurationMs >=
            $minimumDisturbanceMs
        ) {
            $disturbanceAtMs =
                $currentDisturbanceStartMs;

            $disturbanceDurationMs =
                $currentDurationMs;
        }
    }

    $ringFromStartToEnd =
        $hasRingPattern &&
        $disturbanceAtMs === null;

    $cycleScore = min(
        1.0,
        max(
            0,
            count($matchedPulses) - 1
        ) / 2
    );

    $cleanScore =
        $disturbanceAtMs === null
            ? 1.0
            : 0.0;

    $confidence = $hasRingPattern
        ? round(
            ($cycleScore * 0.70) +
            ($cleanScore * 0.30),
            4
        )
        : 0.0;

    $reason = match (true) {
        count($pulses) === 0 =>
        'nenhum_pulso_425hz',

        count($matchedPulses) < 2 =>
        'periodicidade_de_5_segundos_nao_confirmada',

        $disturbanceAtMs !== null =>
        'ring_perturbado_por_outro_audio',

        default =>
        'ring_presente_do_inicio_ao_fim',
    };

    return [
        'has_ring_pattern' => $hasRingPattern,
        'ring_from_start_to_end' =>
            $ringFromStartToEnd,
        'reason' => $reason,
        'confidence' => $confidence,
        'duration_ms' => $durationMs,
        'pulses' => $pulses,
        'matched_pulses' => $matchedPulses,
        'periods_ms' => $periodsMs,
        'disturbance_at_ms' => $disturbanceAtMs,
        'disturbance_duration_ms' =>
            $disturbanceDurationMs,
        'frames' => $frames,
    ];
}
