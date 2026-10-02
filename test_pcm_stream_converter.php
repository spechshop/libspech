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
    [48000, 2, 8000, 1], [44100, 2, 8000, 1], [32000, 2, 8000, 1],
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
