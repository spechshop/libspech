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
if ($channels !== 1) {
    throw new RuntimeException("Only mono/stereo WAV supported for this demo (channels={$channels})");
}

$targets = [8000, 16000, 24000, 48000];
$outDir = extra_out_dir('resample');

foreach ($targets as $dstRate) {
    $pcmOut = extra_resample_pcm16le($pcm, $srcRate, $dstRate);
    $out = "{$outDir}/music_mono_{$dstRate}.wav";
    extra_write_wav_pcm16le($out, $pcmOut, $dstRate, 1);
    echo "Wrote: {$out}" . PHP_EOL;
}

