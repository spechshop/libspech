<?php


function generateSilenceAudio(float $duration, int $sampleRate = 44100, int $channels = 1, int $bitDepth = 16, string $outputFile = 'silence.wav'): bool
{
    if ($duration <= 0) {
        throw new InvalidArgumentException("Duração deve ser maior que zero");
    }

    if (!in_array($channels, [1, 2])) {
        throw new InvalidArgumentException("Número de canais deve ser 1 (mono) ou 2 (estéreo)");
    }

    if (!in_array($bitDepth, [8, 16, 24, 32])) {
        throw new InvalidArgumentException("Profundidade de bits deve ser 8, 16, 24 ou 32");
    }

    $bytesPerSample = $bitDepth / 8;
    $blockAlign = $channels * $bytesPerSample;
    $byteRate = $sampleRate * $blockAlign;

    $numSamples = (int)($duration * $sampleRate);
    $dataSize = $numSamples * $blockAlign;

    $header = pack(
        'a4Va4a4VvvVVvva4V',
        'RIFF',
        36 + $dataSize,
        'WAVE',
        'fmt ',
        16,
        1,
        $channels,
        $sampleRate,
        $byteRate,
        $blockAlign,
        $bitDepth,
        'data',
        $dataSize
    );

    $file = fopen($outputFile, 'wb');
    if ($file === false) {
        throw new RuntimeException("Não foi possível criar o arquivo: {$outputFile}");
    }

    fwrite($file, $header);

    $bufferSize = 8192;
    $silenceBuffer = str_repeat("\0", $bufferSize);
    $remainingBytes = $dataSize;

    while ($remainingBytes > 0) {
        $bytesToWrite = min($bufferSize, $remainingBytes);
        fwrite($file, substr($silenceBuffer, 0, $bytesToWrite));
        $remainingBytes -= $bytesToWrite;
    }

    fclose($file);

    return true;
}

generateSilenceAudio(5*60, 8000, 1, 16, 'silence_5m.wav');