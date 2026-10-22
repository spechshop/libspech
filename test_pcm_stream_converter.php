<?php

declare(strict_types=1);

require_once __DIR__ . '/plugins/Utils/sip/PcmStreamConverter.php';

use libspech\Rtp\PcmStreamConverter;

function pcmCheck(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function convertInChunks(string $pcm, int $sourceRate, int $sourceChannels,
    int $targetRate, int $targetChannels, int $chunkMs): string
{
    $converter = new PcmStreamConverter($sourceRate, $sourceChannels, $targetRate, $targetChannels);
    $samples = intdiv(strlen($pcm), 2 * $sourceChannels);
    $output = '';
    $position = 0;
    $index = 1;
    while ($position < $samples) {
        $end = min($samples, (int)round($index++ * $sourceRate * $chunkMs / 1000));
        $count = $end - $position;
        if ($count <= 0) continue;
        $output .= $converter->push(substr($pcm, $position * 2 * $sourceChannels,
            $count * 2 * $sourceChannels));
        $position = $end;
    }
    return $output . $converter->finish();
}

$cases = [
    [48000, 1, 48000, 2], [48000, 2, 48000, 1],
    [48000, 2, 8000, 1], [44100, 2, 8000, 1], [32000, 2, 8000, 1],
    [48000, 2, 8000, 2], [8000, 2, 48000, 2],
    [24000, 2, 16000, 2], [16000, 2, 24000, 2],
    [16000, 1, 8000, 1], [8000, 1, 16000, 1], [8000, 1, 32000, 1],
    [8000, 1, 44100, 1], [8000, 1, 48000, 1], [44100, 1, 48000, 1],
    [48000, 1, 44100, 1],
];
foreach ($cases as [$sourceRate, $sourceChannels, $targetRate, $targetChannels]) {
    $samples = $sourceRate * 2;
    $pattern = pack('vvvv', 1000, 30000, 50000, 24000);
    $pcm = substr(str_repeat($pattern, (int)ceil($samples * $sourceChannels / 4)),
        0, $samples * $sourceChannels * 2);
    $whole = convertInChunks($pcm, $sourceRate, $sourceChannels, $targetRate, $targetChannels, 2000);
    $expectedBytes = (int)round($samples * $targetRate / $sourceRate) * $targetChannels * 2;
    pcmCheck(strlen($whole) === $expectedBytes, "Wrong duration {$sourceRate}/{$sourceChannels} to {$targetRate}/{$targetChannels}");
    foreach ([5, 10, 20, 30, 40, 60] as $chunkMs) {
        $chunked = convertInChunks($pcm, $sourceRate, $sourceChannels, $targetRate, $targetChannels, $chunkMs);
        pcmCheck($chunked === $whole,
            "Chunk mismatch {$sourceRate}/{$sourceChannels} to {$targetRate}/{$targetChannels} at {$chunkMs}ms");
    }
    echo "PASS {$sourceRate}/{$sourceChannels} -> {$targetRate}/{$targetChannels}\n";
}

function sineStereo(int $rate, int $samples): string
{
    $pcm = '';
    for ($i = 0; $i < $samples; $i++) {
        $left = (int)(12000 * sin(2 * M_PI * 440 * $i / $rate));
        $right = (int)(9000 * sin(2 * M_PI * 1300 * $i / $rate));
        $pcm .= pack('v2', $left, $right);
    }
    return $pcm;
}

function channelSamples(string $pcm, int $channel): array
{
    $samples = [];
    for ($offset = 0, $length = strlen($pcm); $offset + 4 <= $length; $offset += 4) {
        $value = unpack('v', substr($pcm, $offset + 2 * $channel, 2))[1];
        $samples[] = $value >= 0x8000 ? $value - 0x10000 : $value;
    }
    return $samples;
}

function toneAmplitude(array $samples, int $rate, float $frequency): float
{
    // Ignore FIR startup/tail samples, where zero padding is expected.
    $samples = array_slice($samples, min(64, intdiv(count($samples), 4)), -min(64, intdiv(count($samples), 4)));
    $real = $imaginary = 0.0;
    $count = count($samples);
    foreach ($samples as $index => $sample) {
        $phase = 2 * M_PI * $frequency * $index / $rate;
        $real += $sample * cos($phase);
        $imaginary -= $sample * sin($phase);
    }
    return $count === 0 ? 0.0 : 2 * hypot($real, $imaginary) / $count;
}

// Distinct tones make channel swaps or accidental mixing visible.
foreach ([[48000, 8000], [8000, 48000], [24000, 16000], [16000, 24000]] as [$sourceRate, $targetRate]) {
    $sourceSamples = $sourceRate * 3;
    $pcm = sineStereo($sourceRate, $sourceSamples);
    $converted = convertInChunks($pcm, $sourceRate, 2, $targetRate, 2, 20);
    $left = channelSamples($converted, 0);
    $right = channelSamples($converted, 1);
    pcmCheck(count($left) === $targetRate * 3 && count($right) === $targetRate * 3,
        "Stereo duration mismatch {$sourceRate} -> {$targetRate}");
    pcmCheck(toneAmplitude($left, $targetRate, 440) > toneAmplitude($left, $targetRate, 1300) * 4,
        "Left channel tone leaked or was swapped {$sourceRate} -> {$targetRate}");
    pcmCheck(toneAmplitude($right, $targetRate, 1300) > toneAmplitude($right, $targetRate, 440) * 4,
        "Right channel tone leaked or was swapped {$sourceRate} -> {$targetRate}");
    echo "PASS stereo channel separation {$sourceRate} -> {$targetRate}\n";
}

// Interleaving two independent stream objects must not share FIR history.
{
    $rate = 48000;
    $samples = $rate * 5;
    $pcmA = sineStereo($rate, $samples);
    $pcmB = str_repeat(pack('v2', 12000, 12000), $samples);
    $expectedA = convertInChunks($pcmA, $rate, 2, 8000, 2, 20);
    $converterA = new PcmStreamConverter($rate, 2, 8000, 2);
    $converterB = new PcmStreamConverter($rate, 2, 8000, 2);
    $actualA = $actualB = '';
    $packetBytes = intdiv($rate, 50) * 4;
    for ($offset = 0; $offset < strlen($pcmA); $offset += $packetBytes) {
        $actualA .= $converterA->push(substr($pcmA, $offset, $packetBytes));
        $actualB .= $converterB->push(substr($pcmB, $offset, $packetBytes));
    }
    $actualA .= $converterA->finish();
    $actualB .= $converterB->finish();
    pcmCheck($actualA === $expectedA, 'Concurrent stream history contaminated stereo signal');
    pcmCheck(strlen($actualB) === 8000 * 5 * 4, 'Concurrent constant stream lost duration');
    echo "PASS interleaved 20ms streams remain isolated\n";
}

// Thousands of RTP-sized blocks exercise bounded state and exact final duration.
{
    $rate = 48000;
    $packets = 3000;
    $converter = new PcmStreamConverter($rate, 2, 8000, 2);
    $outputBytes = 0;
    $packet = sineStereo($rate, intdiv($rate, 50));
    for ($index = 0; $index < $packets; $index++) {
        $outputBytes += strlen($converter->push($packet));
    }
    $outputBytes += strlen($converter->finish());
    pcmCheck($outputBytes === $packets * intdiv(8000, 50) * 4,
        'Three thousand 20ms packets accumulated duration drift');
    echo "PASS 3000 stereo RTP packets preserve accumulated duration\n";
}

{
    $converter = new PcmStreamConverter(48000, 2, 8000, 2);
    pcmCheck($converter->push('') === '', 'Empty block must remain empty');
    pcmCheck((new PcmStreamConverter(48000, 2, 48000, 2))->push("\x01\x00\x02\x00") === "\x01\x00\x02\x00",
        'Same-rate PCM must remain byte-identical');
    try {
        $converter->push("\x01\x00\x02");
        throw new RuntimeException('Incomplete stereo frame was accepted');
    } catch (InvalidArgumentException) {
        echo "PASS empty, identity, and incomplete frame behavior\n";
    }
}
foreach ([[48000, 2, 8000, 1], [44100, 2, 8000, 1], [8000, 1, 48000, 1]] as
    [$sourceRate, $sourceChannels, $targetRate, $targetChannels]) {
    $samples = $sourceRate * 60;
    $pattern = pack('vvvv', 1000, 30000, 50000, 24000);
    $pcm = substr(str_repeat($pattern, (int)ceil($samples * $sourceChannels / 4)),
        0, $samples * $sourceChannels * 2);
    $result = convertInChunks($pcm, $sourceRate, $sourceChannels, $targetRate, $targetChannels, 20);
    pcmCheck(strlen($result) === $targetRate * 60 * $targetChannels * 2,
        "60-second drift {$sourceRate} to {$targetRate}");
    echo "PASS 60s {$sourceRate}/{$sourceChannels} -> {$targetRate}/{$targetChannels}\n";
}
