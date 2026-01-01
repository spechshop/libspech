<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

foreach (['encodePcmToPcma', 'decodePcmaToPcm', 'encodePcmToPcmu', 'decodePcmuToPcm'] as $fn) {
    if (!function_exists($fn)) {
        throw new RuntimeException("Missing function: {$fn}");
    }
}

$asset = extra_root() . '/extra/assets/music.wav';
$wav = extra_read_wav_pcm16le($asset);

$pcm = $wav['pcm'];
$srcRate = $wav['sampleRate'];
$channels = $wav['channels'];

if ($channels === 2) {
    $pcm = extra_downmix_to_mono($pcm);
    $channels = 1;
}

$pcm8 = extra_resample_pcm16le($pcm, $srcRate, 8000);

// PCMA roundtrip
$pcma = encodePcmToPcma($pcm8);
$pcmFromPcma = decodePcmaToPcm($pcma);
$snrPcma = extra_snr_db($pcm8, $pcmFromPcma);

// PCMU roundtrip
$pcmu = encodePcmToPcmu($pcm8);
$pcmFromPcmu = decodePcmuToPcm($pcmu);
$snrPcmu = extra_snr_db($pcm8, $pcmFromPcmu);

$outDir = extra_out_dir('codecs');
extra_write_wav_pcm16le("{$outDir}/music_pcm8_original.wav", $pcm8, 8000, 1);
extra_write_wav_pcm16le("{$outDir}/music_pcm8_from_pcma.wav", $pcmFromPcma, 8000, 1);
extra_write_wav_pcm16le("{$outDir}/music_pcm8_from_pcmu.wav", $pcmFromPcmu, 8000, 1);

echo "SNR (PCMA): " . (is_infinite($snrPcma) ? 'INF' : round($snrPcma, 2) . ' dB') . PHP_EOL;
echo "SNR (PCMU): " . (is_infinite($snrPcmu) ? 'INF' : round($snrPcmu, 2) . ' dB') . PHP_EOL;
echo "Wrote: {$outDir}/music_pcm8_original.wav" . PHP_EOL;
echo "Wrote: {$outDir}/music_pcm8_from_pcma.wav" . PHP_EOL;
echo "Wrote: {$outDir}/music_pcm8_from_pcmu.wav" . PHP_EOL;

