<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

$asset = extra_root() . '/extra/assets/music.wav';
$wav = extra_read_wav_pcm16le($asset);

$pcm = $wav['pcm'];
$srcRate = $wav['sampleRate'];
$channels = $wav['channels'];

if ($channels === 2) {
    $pcm = extra_downmix_to_mono($pcm);
    $channels = 1;
}

// Baseline: resample 44.1k->8k->44.1k (or whatever source is) and measure SNR.
$pcm8 = extra_resample_pcm16le($pcm, $srcRate, 8000);

echo "Source: {$srcRate} Hz, channels={$channels}" . PHP_EOL;

// Resample quality demo: 8k -> X -> 8k (same target rate for comparison).
foreach ([16000, 24000, 48000] as $midRate) {
    $up = extra_resample_pcm16le($pcm8, 8000, $midRate);
    $back = extra_resample_pcm16le($up, $midRate, 8000);
    $snrResample = extra_snr_db_aligned($pcm8, $back, 400, 20000);
    echo "SNR resample roundtrip (8000 -> {$midRate} -> 8000, aligned): " . (is_infinite($snrResample) ? 'INF' : round($snrResample, 2) . ' dB') . PHP_EOL;
}

// Codec roundtrips at 8 kHz mono (if available)
if (function_exists('encodePcmToPcma') && function_exists('decodePcmaToPcm')) {
    $pcma = encodePcmToPcma($pcm8);
    $pcmFromPcma = decodePcmaToPcm($pcma);
    $snrPcma = extra_snr_db($pcm8, $pcmFromPcma);
    echo "SNR PCMA roundtrip @ 8k: " . (is_infinite($snrPcma) ? 'INF' : round($snrPcma, 2) . ' dB') . PHP_EOL;
}

if (function_exists('encodePcmToPcmu') && function_exists('decodePcmuToPcm')) {
    $pcmu = encodePcmToPcmu($pcm8);
    $pcmFromPcmu = decodePcmuToPcm($pcmu);
    $snrPcmu = extra_snr_db($pcm8, $pcmFromPcmu);
    echo "SNR PCMU roundtrip @ 8k: " . (is_infinite($snrPcmu) ? 'INF' : round($snrPcmu, 2) . ' dB') . PHP_EOL;
}
