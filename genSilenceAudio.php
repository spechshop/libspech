<?php

/**
 * Gera um arquivo de áudio com silêncio
 * 
 * @param float $duration Duração em segundos
 * @param int $sampleRate Taxa de amostragem (Hz) - padrão: 44100
 * @param int $channels Número de canais (1=mono, 2=estéreo) - padrão: 1
 * @param int $bitDepth Profundidade de bits (8, 16, 24, 32) - padrão: 16
 * @param string $outputFile Caminho do arquivo de saída
 * @return bool Retorna true se o arquivo foi criado com sucesso
 */
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
    
    // Cabeçalho WAV
    $header = pack(
        'a4Va4a4VvvVVvva4V',
        'RIFF',
        36 + $dataSize,
        'WAVE',
        'fmt ',
        16,                    // Tamanho do chunk fmt
        1,                     // Formato de áudio (1 = PCM)
        $channels,             // Número de canais
        $sampleRate,           // Taxa de amostragem
        $byteRate,             // Byte rate
        $blockAlign,           // Block align
        $bitDepth,             // Bits por amostra
        'data',
        $dataSize
    );
    
    // Cria o arquivo
    $file = fopen($outputFile, 'wb');
    if ($file === false) {
        throw new RuntimeException("Não foi possível criar o arquivo: {$outputFile}");
    }
    
    // Escreve o cabeçalho
    fwrite($file, $header);
    
    // Escreve dados de silêncio (zeros)
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