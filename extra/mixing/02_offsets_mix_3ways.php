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

$pcm8 = extra_resample_pcm16le($pcm, $srcRate, 8000);

// Create 3 "channels" with different offsets and gains, then mix using libspech utility.
$ch1 = extra_scale_pcm16le($pcm8, 0.9);
$ch2 = extra_silence_ms(90, 8000, 1) . extra_scale_pcm16le($pcm8, 0.6);
$ch3 = extra_silence_ms(180, 8000, 1) . extra_scale_pcm16le($pcm8, 0.45);

if (!function_exists('mixAudioChannels')) {
    throw new RuntimeException('mixAudioChannels() not available in this environment.');
}

$mixed = mixAudioChannels([$ch1, $ch2, $ch3], 8000);

$outDir = extra_out_dir('mixing');
extra_write_wav_pcm16le("{$outDir}/music_mix3_offsets_8k.wav", $mixed, 8000, 1);
echo "Wrote: {$outDir}/music_mix3_offsets_8k.wav" . PHP_EOL;

