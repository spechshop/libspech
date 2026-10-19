<?php

declare(strict_types=1);

const SOURCE_SAMPLE_RATE = 8000;
const TARGET_SAMPLE_RATE = 48000;
const CHANNELS = 1;
const BITS_PER_SAMPLE = 16;

function failTest(string $message): never
{
    fwrite(STDERR, "FAIL {$message}" . PHP_EOL);
    exit(1);
}

function writeAll($stream, string $data): void
{
    $written = fwrite($stream, $data);
    if ($written !== strlen($data)) {
        throw new RuntimeException('Não foi possível gravar completamente o WAV.');
    }
}

function wavHeader(int $pcmBytes, int $sampleRate): string
{
    $blockAlign = CHANNELS * intdiv(BITS_PER_SAMPLE, 8);
    $byteRate = $sampleRate * $blockAlign;

    return pack('a4V', 'RIFF', 36 + $pcmBytes)
        . 'WAVE'
        . pack('a4VvvVVvv', 'fmt ', 16, 1, CHANNELS, $sampleRate, $byteRate, $blockAlign, BITS_PER_SAMPLE)
        . pack('a4V', 'data', $pcmBytes);
}

if (!extension_loaded('bcg729') || !function_exists('resampler')) {
    failTest('A extensão bcg729 e a função resampler() precisam estar carregadas.');
}

$sourcePath = dirname(__DIR__) . '/8khz_mono_ffmpeg.wav';
$outputPath = $argv[1] ?? (__DIR__ . '/output/resampler/8khz_mono_ffmpeg_48khz_mono.wav');
$input = fopen($sourcePath, 'rb');
if ($input === false) {
    failTest("Não foi possível abrir o arquivo de entrada: {$sourcePath}");
}

$output = null;

try {
    $fileSize = filesize($sourcePath);
    $containerHeader = fread($input, 12);
    if ($fileSize === false || $containerHeader === false || strlen($containerHeader) !== 12) {
        throw new RuntimeException('Arquivo WAV incompleto.');
    }

    $container = unpack('a4riff/VriffSize/a4wave', $containerHeader);
    if ($container['riff'] !== 'RIFF' || $container['wave'] !== 'WAVE') {
        throw new RuntimeException('O arquivo de entrada não é RIFF/WAVE.');
    }

    $format = null;
    $dataOffset = null;
    $dataBytes = null;

    while (ftell($input) + 8 <= $fileSize) {
        $chunkHeader = fread($input, 8);
        if ($chunkHeader === false || strlen($chunkHeader) !== 8) {
            throw new RuntimeException('Cabeçalho de chunk WAV incompleto.');
        }

        $chunk = unpack('a4id/Vsize', $chunkHeader);
        $chunkDataOffset = ftell($input);
        $chunkEnd = $chunkDataOffset + $chunk['size'];
        if ($chunkEnd > $fileSize) {
            throw new RuntimeException("Chunk WAV '{$chunk['id']}' excede o tamanho do arquivo.");
        }

        if ($chunk['id'] === 'fmt ') {
            if ($chunk['size'] < 16) {
                throw new RuntimeException('Chunk fmt inválido.');
            }

            $formatData = fread($input, 16);
            if ($formatData === false || strlen($formatData) !== 16) {
                throw new RuntimeException('Dados do chunk fmt incompletos.');
            }

            $format = unpack('vaudioFormat/vchannels/VsampleRate/VbyteRate/vblockAlign/vbitsPerSample', $formatData);
        } elseif ($chunk['id'] === 'data') {
            $dataOffset = $chunkDataOffset;
            $dataBytes = $chunk['size'];
        }

        if (fseek($input, $chunkEnd + ($chunk['size'] & 1), SEEK_SET) !== 0) {
            throw new RuntimeException('Não foi possível avançar para o próximo chunk WAV.');
        }
    }

    if ($format === null || $dataOffset === null || $dataBytes === null) {
        throw new RuntimeException('O WAV precisa conter os chunks fmt e data.');
    }
    if ($format['audioFormat'] !== 1 || $format['channels'] !== CHANNELS
        || $format['sampleRate'] !== SOURCE_SAMPLE_RATE || $format['bitsPerSample'] !== BITS_PER_SAMPLE) {
        throw new RuntimeException('Entrada esperada: PCM16LE mono a 8 kHz.');
    }
    if ($format['blockAlign'] !== 2 || ($dataBytes & 1) !== 0) {
        throw new RuntimeException('O chunk data não contém amostras PCM16 mono completas.');
    }

    $sourceSamples = intdiv($dataBytes, 2);
    $targetBytes = $sourceSamples * intdiv(TARGET_SAMPLE_RATE, SOURCE_SAMPLE_RATE) * 2;
    if ($targetBytes > 0xFFFFFFFF - 36) {
        throw new RuntimeException('O WAV convertido excede o limite do formato RIFF.');
    }

    if (fseek($input, $dataOffset, SEEK_SET) !== 0) {
        throw new RuntimeException('Não foi possível posicionar no PCM de entrada.');
    }
    $pcm = fread($input, $dataBytes);
    if ($pcm === false || strlen($pcm) !== $dataBytes) {
        throw new RuntimeException('Leitura incompleta do PCM de entrada.');
    }

    // false mantém PCM little-endian, o mesmo formato usado pelo WAV de entrada.
    $converted = resampler($pcm, SOURCE_SAMPLE_RATE, TARGET_SAMPLE_RATE, false);
    if (strlen($converted) !== $targetBytes) {
        throw new RuntimeException(sprintf(
            'resampler() retornou %d bytes; esperado %d para preservar a duração.',
            strlen($converted),
            $targetBytes
        ));
    }

    $outputDirectory = dirname($outputPath);
    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
        throw new RuntimeException("Não foi possível criar a pasta: {$outputDirectory}");
    }

    $output = fopen($outputPath, 'wb');
    if ($output === false) {
        throw new RuntimeException("Não foi possível criar o arquivo de saída: {$outputPath}");
    }

    writeAll($output, wavHeader($targetBytes, TARGET_SAMPLE_RATE));
    writeAll($output, $converted);
    if (fclose($output) === false) {
        $output = null;
        throw new RuntimeException('Falha ao finalizar o WAV convertido.');
    }
    $output = null;

    $outputSize = filesize($outputPath);
    $convertedFile = fopen($outputPath, 'rb');
    if ($outputSize !== 44 + $targetBytes || $convertedFile === false) {
        throw new RuntimeException('O tamanho final do WAV convertido está incorreto.');
    }
    $convertedHeader = fread($convertedFile, 44);
    fclose($convertedFile);
    if ($convertedHeader === false || strlen($convertedHeader) !== 44) {
        throw new RuntimeException('Cabeçalho de saída incompleto.');
    }

    $outputFormat = unpack('vchannels/VsampleRate', $convertedHeader, 22);
    $outputBits = unpack('vbitsPerSample', $convertedHeader, 34);
    if ($outputFormat['channels'] !== CHANNELS || $outputFormat['sampleRate'] !== TARGET_SAMPLE_RATE
        || $outputBits['bitsPerSample'] !== BITS_PER_SAMPLE) {
        throw new RuntimeException('O cabeçalho final não descreve PCM16 mono a 48 kHz.');
    }

    echo sprintf(
        "PASS %d amostras a %d Hz reamostradas para %d amostras mono a %d Hz: %s (%d bytes PCM)\n",
        $sourceSamples,
        SOURCE_SAMPLE_RATE,
        intdiv(strlen($converted), 2),
        TARGET_SAMPLE_RATE,
        $outputPath,
        strlen($converted)
    );
} catch (Throwable $error) {
    failTest($error->getMessage());
} finally {
    if (is_resource($output)) {
        fclose($output);
    }
    if (is_resource($input)) {
        fclose($input);
    }
}
