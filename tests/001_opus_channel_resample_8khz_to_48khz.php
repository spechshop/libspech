<?php

declare(strict_types=1);

const SOURCE_SAMPLE_RATE = 8000;
const TARGET_SAMPLE_RATE = 48000;
const CHANNELS = 1;
const BITS_PER_SAMPLE = 16;
const MAX_SOURCE_SAMPLES_PER_CALL = 8192;
const MAX_DOWN_SAMPLE_SAMPLES_PER_CALL = 240000;
const OPUS_FRAME_SAMPLES = 960;

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
        throw new RuntimeException('Não foi possível gravar completamente o WAV convertido.');
    }
}

function wavHeader(int $pcmBytes, int $sampleRate, int $channels = CHANNELS): string
{
    $blockAlign = $channels * intdiv(BITS_PER_SAMPLE, 8);
    $byteRate = $sampleRate * $blockAlign;

    return pack('a4V', 'RIFF', 36 + $pcmBytes)
        . 'WAVE'
        . pack('a4VvvVVvv', 'fmt ', 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, BITS_PER_SAMPLE)
        . pack('a4V', 'data', $pcmBytes);
}

if (!extension_loaded('opus') || !class_exists('opusChannel')
    || !extension_loaded('psampler') || !class_exists('ByteBuffer')) {
    failTest('As extensões opus e psampler e as classes opusChannel e ByteBuffer precisam estar carregadas.');
}

$sourcePath = dirname(__DIR__) . '/8khz_mono_ffmpeg.wav';
$outputDirectory = $argv[1] ?? (__DIR__ . '/output/opus');
$output48Path = $outputDirectory . '/8khz_mono_ffmpeg_opus_48khz_mono.wav';
$outputStereo48Path = $outputDirectory . '/8khz_mono_ffmpeg_opus_48khz_stereo.wav';
$output8Path = $outputDirectory . '/8khz_mono_ffmpeg_opus_back_8khz_mono.wav';
$outputStereo8Path = $outputDirectory . '/8khz_mono_ffmpeg_opus_stereo_back_8khz_mono.wav';
$input = fopen($sourcePath, 'rb');
if ($input === false) {
    failTest("Não foi possível abrir o arquivo de entrada: {$sourcePath}");
}

$output = null;
$stereoOutput = null;
$downsampleOutput = null;
$stereoDownsampleOutput = null;
$roundTripInput = null;
$recoveredMonoBuffer = null;
$resampler = null;
$encoder = null;
$decoder = null;
$stereoProcessor = null;
$stereoEncoder = null;
$stereoDecoder = null;

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

    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
        throw new RuntimeException("Não foi possível criar a pasta: {$outputDirectory}");
    }

    $output = fopen($output48Path, 'wb');
    if ($output === false) {
        throw new RuntimeException("Não foi possível criar o arquivo de saída: {$output48Path}");
    }

    writeAll($output, wavHeader($targetBytes, TARGET_SAMPLE_RATE));
    $stereoOutput = fopen($outputStereo48Path, 'wb');
    if ($stereoOutput === false) {
        throw new RuntimeException("Não foi possível criar o arquivo de saída: {$outputStereo48Path}");
    }
    writeAll($stereoOutput, wavHeader($targetBytes * 2, TARGET_SAMPLE_RATE, 2));
    $recoveredMonoBuffer = fopen('php://temp/maxmemory:16777216', 'w+b');
    if ($recoveredMonoBuffer === false) {
        throw new RuntimeException('Não foi possível criar o buffer PCM mono recuperado do stereo.');
    }

    if (fseek($input, $dataOffset, SEEK_SET) !== 0) {
        throw new RuntimeException('Não foi possível posicionar no PCM de entrada.');
    }

    $remainingBytes = $dataBytes;
    $writtenPcmBytes = 0;
    $chunkBytes = MAX_SOURCE_SAMPLES_PER_CALL * 2;
    $frameBytes = OPUS_FRAME_SAMPLES * 2;
    $frameBuffer = new ByteBuffer($frameBytes * 2);
    $resampler = new opusChannel(TARGET_SAMPLE_RATE, CHANNELS);
    $encoder = new opusChannel(TARGET_SAMPLE_RATE, CHANNELS);
    $decoder = new opusChannel(TARGET_SAMPLE_RATE, CHANNELS);
    $stereoProcessor = new opusChannel(TARGET_SAMPLE_RATE, 2);
    $stereoEncoder = new opusChannel(TARGET_SAMPLE_RATE, 2);
    $stereoDecoder = new opusChannel(TARGET_SAMPLE_RATE, 2);
    $opusFrames = 0;
    $stereoPcmBytes = 0;

    while ($remainingBytes > 0) {
        $readBytes = min($chunkBytes, $remainingBytes);
        $pcm = fread($input, $readBytes);
        if ($pcm === false || strlen($pcm) !== $readBytes) {
            throw new RuntimeException('Leitura incompleta do PCM de entrada.');
        }

        // A API nativa limita a entrada a 8192 amostras por chamada.
        $resampled = $resampler->resample($pcm, SOURCE_SAMPLE_RATE, TARGET_SAMPLE_RATE);
        $resampler->reset();
        $expectedChunkBytes = intdiv($readBytes, 2) * 6 * 2;
        if (strlen($resampled) !== $expectedChunkBytes) {
            throw new RuntimeException(sprintf(
                'opusChannel::resample retornou %d bytes para um bloco que deveria gerar %d bytes.',
                strlen($resampled),
                $expectedChunkBytes
            ));
        }

        $frameBuffer->append($resampled);
        while ($frameBuffer->has($frameBytes)) {
            $frame = $frameBuffer->pop($frameBytes);
            $packet = $encoder->encode($frame, TARGET_SAMPLE_RATE);
            if ($packet === '') {
                throw new RuntimeException('opusChannel::encode retornou um pacote vazio.');
            }

            $decoded = $decoder->decode($packet, TARGET_SAMPLE_RATE);
            if (strlen($decoded) !== $frameBytes) {
                throw new RuntimeException(sprintf(
                    'opusChannel::decode retornou %d bytes; esperado %d por frame.',
                    strlen($decoded),
                    $frameBytes
                ));
            }

            writeAll($output, $decoded);
            $writtenPcmBytes += strlen($decoded);

            $stereoFrame = $stereoProcessor->monoToStereo($decoded);
            if (strlen($stereoFrame) !== $frameBytes * 2) {
                throw new RuntimeException('monoToStereo não produziu um frame PCM estéreo completo.');
            }
            $stereoPacket = $stereoEncoder->encode($stereoFrame, TARGET_SAMPLE_RATE);
            if ($stereoPacket === '') {
                throw new RuntimeException('opusChannel::encode retornou pacote vazio para o frame estéreo.');
            }
            $decodedStereo = $stereoDecoder->decode($stereoPacket, TARGET_SAMPLE_RATE);
            if (strlen($decodedStereo) !== $frameBytes * 2) {
                throw new RuntimeException('opusChannel::decode não recuperou um frame estéreo completo.');
            }
            writeAll($stereoOutput, $decodedStereo);
            $stereoPcmBytes += strlen($decodedStereo);

            $recoveredMono = $stereoProcessor->stereoToMono($decodedStereo);
            if (strlen($recoveredMono) !== $frameBytes) {
                throw new RuntimeException('stereoToMono não recuperou um frame PCM mono completo.');
            }
            writeAll($recoveredMonoBuffer, $recoveredMono);
            $opusFrames++;
        }

        $remainingBytes -= $readBytes;
    }

    $tailBytes = $frameBuffer->length();
    $expectedTailBytes = $targetBytes - $writtenPcmBytes;
    if ($tailBytes !== $expectedTailBytes) {
        throw new RuntimeException("Tamanho residual inesperado: {$tailBytes} bytes, esperado {$expectedTailBytes}.");
    }
    if ($tailBytes > 0) {
        $lastFrame = str_pad($frameBuffer->pop($tailBytes), $frameBytes, "\x00");
        $packet = $encoder->encode($lastFrame, TARGET_SAMPLE_RATE);
        if ($packet === '') {
            throw new RuntimeException('opusChannel::encode retornou um pacote vazio para o último frame.');
        }

        $decoded = $decoder->decode($packet, TARGET_SAMPLE_RATE);
        if (strlen($decoded) !== $frameBytes) {
            throw new RuntimeException('opusChannel::decode retornou tamanho incorreto para o último frame.');
        }

        $written = fwrite($output, $decoded, $tailBytes);
        if ($written !== $tailBytes) {
            throw new RuntimeException('Não foi possível gravar o último frame decodificado.');
        }
        $writtenPcmBytes += $written;

        $stereoFrame = $stereoProcessor->monoToStereo($decoded);
        $stereoPacket = $stereoEncoder->encode($stereoFrame, TARGET_SAMPLE_RATE);
        if ($stereoPacket === '') {
            throw new RuntimeException('opusChannel::encode retornou pacote vazio para o último frame estéreo.');
        }
        $decodedStereo = $stereoDecoder->decode($stereoPacket, TARGET_SAMPLE_RATE);
        if (strlen($decodedStereo) !== $frameBytes * 2) {
            throw new RuntimeException('opusChannel::decode retornou tamanho incorreto para o último frame estéreo.');
        }
        $stereoTailBytes = $tailBytes * 2;
        $stereoWritten = fwrite($stereoOutput, $decodedStereo, $stereoTailBytes);
        if ($stereoWritten !== $stereoTailBytes) {
            throw new RuntimeException('Não foi possível gravar o último frame estéreo.');
        }
        $stereoPcmBytes += $stereoWritten;

        $recoveredMono = $stereoProcessor->stereoToMono($decodedStereo);
        if (strlen($recoveredMono) !== $frameBytes) {
            throw new RuntimeException('stereoToMono não recuperou o último frame PCM mono.');
        }
        writeAll($recoveredMonoBuffer, $recoveredMono, $tailBytes);
        $opusFrames++;
    }

    if ($writtenPcmBytes !== $targetBytes) {
        throw new RuntimeException("Tamanho decodificado inesperado: {$writtenPcmBytes} bytes, esperado {$targetBytes}.");
    }
    if ($stereoPcmBytes !== $targetBytes * 2) {
        throw new RuntimeException("Tamanho estéreo inesperado: {$stereoPcmBytes} bytes, esperado " . ($targetBytes * 2) . '.');
    }

    if (fclose($stereoOutput) === false) {
        $stereoOutput = null;
        throw new RuntimeException('Falha ao finalizar o WAV estéreo a 48 kHz.');
    }
    $stereoOutput = null;

    if (fclose($output) === false) {
        $output = null;
        throw new RuntimeException('Falha ao finalizar o WAV convertido.');
    }
    $output = null;

    $convertedFileSize = filesize($output48Path);
    $convertedFile = fopen($output48Path, 'rb');
    if ($convertedFileSize !== 44 + $targetBytes || $convertedFile === false) {
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

    // Reabre o WAV 48 kHz decodificado do Opus e reamostra o PCM dele de volta para 8 kHz.
    $roundTripInput = fopen($output48Path, 'rb');
    if ($roundTripInput === false) {
        throw new RuntimeException('Não foi possível abrir o PCM do WAV Opus a 48 kHz.');
    }
    if (fseek($roundTripInput, 44, SEEK_SET) !== 0) {
        throw new RuntimeException('Não foi possível posicionar no PCM do WAV Opus a 48 kHz.');
    }

    $target8Bytes = $sourceSamples * 2;
    $downsampleOutput = fopen($output8Path, 'wb');
    if ($downsampleOutput === false) {
        fclose($roundTripInput);
        throw new RuntimeException("Não foi possível criar o arquivo de saída: {$output8Path}");
    }
    writeAll($downsampleOutput, wavHeader($target8Bytes, SOURCE_SAMPLE_RATE));
    $stereoDownsampleOutput = fopen($outputStereo8Path, 'wb');
    if ($stereoDownsampleOutput === false) {
        fclose($roundTripInput);
        $roundTripInput = null;
        throw new RuntimeException("Não foi possível criar o arquivo de saída: {$outputStereo8Path}");
    }
    writeAll($stereoDownsampleOutput, wavHeader($target8Bytes, SOURCE_SAMPLE_RATE));

    if (ftell($recoveredMonoBuffer) !== $targetBytes || fseek($recoveredMonoBuffer, 0, SEEK_SET) !== 0) {
        throw new RuntimeException('O PCM mono recuperado do stereo não preservou o tamanho esperado.');
    }

    $remaining48Bytes = $targetBytes;
    $writtenPcm8Bytes = 0;
    $writtenStereoPcm8Bytes = 0;
    $downsampleChunkBytes = MAX_DOWN_SAMPLE_SAMPLES_PER_CALL * 2;
    while ($remaining48Bytes > 0) {
        $readBytes = min($downsampleChunkBytes, $remaining48Bytes);
        $readBytes -= $readBytes % 12; // PCM16 mono, com número de amostras divisível por 6.
        if ($readBytes <= 0) {
            throw new RuntimeException('O último bloco 48 kHz não contém amostras completas para 8 kHz.');
        }

        $pcm48 = fread($roundTripInput, $readBytes);
        if ($pcm48 === false || strlen($pcm48) !== $readBytes) {
            throw new RuntimeException('Leitura incompleta do WAV Opus a 48 kHz.');
        }
        $recoveredPcm48 = fread($recoveredMonoBuffer, $readBytes);
        if ($recoveredPcm48 === false || strlen($recoveredPcm48) !== $readBytes) {
            throw new RuntimeException('Leitura incompleta do PCM mono recuperado do stereo.');
        }

        $pcm8 = $resampler->resample($pcm48, TARGET_SAMPLE_RATE, SOURCE_SAMPLE_RATE);
        $resampler->reset();
        $expectedBytes = intdiv($readBytes, 12) * 2;
        if (strlen($pcm8) !== $expectedBytes) {
            throw new RuntimeException(sprintf(
                'opusChannel::resample retornou %d bytes no retorno para 8 kHz; esperado %d.',
                strlen($pcm8),
                $expectedBytes
            ));
        }

        $recoveredPcm8 = $resampler->resample($recoveredPcm48, TARGET_SAMPLE_RATE, SOURCE_SAMPLE_RATE);
        $resampler->reset();
        if (strlen($recoveredPcm8) !== $expectedBytes) {
            throw new RuntimeException(sprintf(
                'opusChannel::resample retornou %d bytes para o mono recuperado; esperado %d.',
                strlen($recoveredPcm8),
                $expectedBytes
            ));
        }

        writeAll($downsampleOutput, $pcm8);
        writeAll($stereoDownsampleOutput, $recoveredPcm8);
        $writtenPcm8Bytes += strlen($pcm8);
        $writtenStereoPcm8Bytes += strlen($recoveredPcm8);
        $remaining48Bytes -= $readBytes;
    }
    fclose($roundTripInput);
    $roundTripInput = null;

    if ($writtenPcm8Bytes !== $target8Bytes) {
        throw new RuntimeException("Tamanho reamostrado para 8 kHz inesperado: {$writtenPcm8Bytes} bytes, esperado {$target8Bytes}.");
    }
    if ($writtenStereoPcm8Bytes !== $target8Bytes) {
        throw new RuntimeException("Tamanho mono recuperado em 8 kHz inesperado: {$writtenStereoPcm8Bytes} bytes, esperado {$target8Bytes}.");
    }
    if (fclose($downsampleOutput) === false) {
        $downsampleOutput = null;
        throw new RuntimeException('Falha ao finalizar o WAV convertido de volta para 8 kHz.');
    }
    $downsampleOutput = null;
    if (fclose($stereoDownsampleOutput) === false) {
        $stereoDownsampleOutput = null;
        throw new RuntimeException('Falha ao finalizar o WAV mono recuperado do stereo.');
    }
    $stereoDownsampleOutput = null;

    if (filesize($output8Path) !== 44 + $target8Bytes
        || filesize($outputStereo8Path) !== 44 + $target8Bytes
        || filesize($outputStereo48Path) !== 44 + $targetBytes * 2) {
        throw new RuntimeException('O tamanho final do WAV 8 kHz está incorreto.');
    }

    echo sprintf(
        "PASS %d frames Opus; mono 48 kHz: %s; estéreo 48 kHz: %s; retorno mono 8 kHz: %s; recuperação estéreo→mono 8 kHz: %s\n",
        $opusFrames,
        $output48Path,
        $outputStereo48Path,
        $output8Path,
        $outputStereo8Path
    );
} catch (Throwable $error) {
    failTest($error->getMessage());
} finally {
    if (is_resource($output)) {
        fclose($output);
    }
    if (is_resource($stereoOutput)) {
        fclose($stereoOutput);
    }
    if (is_resource($downsampleOutput)) {
        fclose($downsampleOutput);
    }
    if (is_resource($stereoDownsampleOutput)) {
        fclose($stereoDownsampleOutput);
    }
    if (is_resource($roundTripInput)) {
        fclose($roundTripInput);
    }
    if (is_resource($recoveredMonoBuffer)) {
        fclose($recoveredMonoBuffer);
    }
    if (is_resource($input)) {
        fclose($input);
    }
    if ($resampler instanceof opusChannel) {
        $resampler->destroy();
    }
    if ($encoder instanceof opusChannel) {
        $encoder->destroy();
    }
    if ($decoder instanceof opusChannel) {
        $decoder->destroy();
    }
    if ($stereoProcessor instanceof opusChannel) {
        $stereoProcessor->destroy();
    }
    if ($stereoEncoder instanceof opusChannel) {
        $stereoEncoder->destroy();
    }
    if ($stereoDecoder instanceof opusChannel) {
        $stereoDecoder->destroy();
    }
}
