<?php

declare(strict_types=1);

const SOURCE_SAMPLE_RATE = 8000;
const TARGET_SAMPLE_RATE = 48000;
const CHANNELS = 1;
const BITS_PER_SAMPLE = 16;
const G729_FRAME_BYTES = 160; // 80 amostras PCM16 mono, 10 ms.
const G729_PACKET_BYTES = 10;

function failTest(string $message): never
{
    fwrite(STDERR, "FAIL {$message}" . PHP_EOL);
    exit(1);
}

function writeAll($stream, string $data, ?int $length = null): void
{
    $length ??= strlen($data);
    $written = fwrite($stream, $data, $length);
    if ($written !== $length) {
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

if (!extension_loaded('bcg729') || !class_exists('bcg729Channel') || !function_exists('resampler')) {
    failTest('A extensão bcg729, a classe bcg729Channel e a função resampler() precisam estar carregadas.');
}

$sourcePath = dirname(__DIR__) . '/8khz_mono_ffmpeg.wav';
$outputPath = $argv[1] ?? (__DIR__ . '/output/bcg729/8khz_mono_ffmpeg_48khz_mono.wav');
$input = fopen($sourcePath, 'rb');
if ($input === false) {
    failTest("Não foi possível abrir o arquivo de entrada: {$sourcePath}");
}

$decodedBuffer = fopen('php://temp/maxmemory:16777216', 'w+b');
if ($decodedBuffer === false) {
    fclose($input);
    failTest('Não foi possível criar o buffer PCM temporário.');
}

$output = null;
$encoder = null;
$decoder = null;

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

    $encoder = new bcg729Channel();
    $decoder = new bcg729Channel();
    $remainingBytes = $dataBytes;
    $codecFrames = 0;

    while ($remainingBytes > 0) {
        $readBytes = min(G729_FRAME_BYTES, $remainingBytes);
        $pcmFrame = fread($input, $readBytes);
        if ($pcmFrame === false || strlen($pcmFrame) !== $readBytes) {
            throw new RuntimeException('Leitura incompleta do PCM de entrada.');
        }

        $codecFrame = str_pad($pcmFrame, G729_FRAME_BYTES, "\x00");
        $packet = $encoder->encode($codecFrame);
        if (strlen($packet) !== G729_PACKET_BYTES) {
            throw new RuntimeException(sprintf(
                'bcg729Channel::encode retornou %d bytes; esperado %d por frame.',
                strlen($packet),
                G729_PACKET_BYTES
            ));
        }

        $decoded = $decoder->decode($packet);
        if (strlen($decoded) !== G729_FRAME_BYTES) {
            throw new RuntimeException(sprintf(
                'bcg729Channel::decode retornou %d bytes; esperado %d por frame.',
                strlen($decoded),
                G729_FRAME_BYTES
            ));
        }

        writeAll($decodedBuffer, $decoded, $readBytes);
        $remainingBytes -= $readBytes;
        $codecFrames++;
    }

    if (ftell($decodedBuffer) !== $dataBytes || fseek($decodedBuffer, 0, SEEK_SET) !== 0) {
        throw new RuntimeException('O PCM decodificado não preservou o tamanho da entrada.');
    }
    $decodedPcm = stream_get_contents($decodedBuffer);
    if ($decodedPcm === false || strlen($decodedPcm) !== $dataBytes) {
        throw new RuntimeException('Não foi possível ler todo o PCM decodificado.');
    }

    // G.729 decodifica para PCM de 8 kHz; resampler() eleva esse PCM para 48 kHz.
    $converted = resampler($decodedPcm, SOURCE_SAMPLE_RATE, TARGET_SAMPLE_RATE, false);
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

    if (filesize($outputPath) !== 44 + $targetBytes) {
        throw new RuntimeException('O tamanho final do WAV convertido está incorreto.');
    }

    echo sprintf(
        "PASS %d frames G.729 decodificados e reamostrados: %d amostras mono a %d Hz em %s (%d bytes PCM)\n",
        $codecFrames,
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
    if (is_resource($decodedBuffer)) {
        fclose($decodedBuffer);
    }
    if ($encoder instanceof bcg729Channel) {
        $encoder->close();
    }
    if ($decoder instanceof bcg729Channel) {
        $decoder->close();
    }
}
