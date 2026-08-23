<?php

declare(strict_types=1);

namespace libspech\RtpRuntime;

/** Minimal PCM helpers shared by the local media server and the Agent bundle. */
function monoToStereo(string $pcmData): string
{
    $stereo = '';
    $length = strlen($pcmData) - (strlen($pcmData) % 2);
    for ($offset = 0; $offset < $length; $offset += 2) {
        $sample = substr($pcmData, $offset, 2);
        $stereo .= $sample . $sample;
    }
    return $stereo !== '' ? $stereo : $pcmData;
}

function stereoToMono(string $pcmData): string
{
    $mono = '';
    $length = strlen($pcmData) - (strlen($pcmData) % 4);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $left = unpack('s', substr($pcmData, $offset, 2))[1];
        $right = unpack('s', substr($pcmData, $offset + 2, 2))[1];
        $mono .= pack('s', (int)(($left + $right) / 2));
    }
    return $mono !== '' ? $mono : $pcmData;
}

function volumeAverage(string $pcm, int $sampleRate = 8000): float
{
    if ($pcm === '') return 0.0;
    $sampleCount = max(1, (int)round($sampleRate * 0.010));
    $length = $sampleCount * 2;
    if (strlen($pcm) < $length) return 0.1;
    $sum = 0;
    for ($offset = 0; $offset < $length; $offset += 2) {
        $sample = unpack('s', substr($pcm, $offset, 2))[1];
        $sum += $sample * $sample;
    }
    $normalized = sqrt($sum / $sampleCount) / 32768.0;
    return max(1, min(100, round($normalized * 100, 2)));
}
