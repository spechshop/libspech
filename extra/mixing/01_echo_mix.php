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
$pcm48 = extra_resample_pcm16le($pcm, $srcRate, 48000);

$delayMs = 140;
$decay = 0.55;

$delayed = extra_silence_ms($delayMs, 48000, 1) . extra_scale_pcm16le($pcm48, $decay);
$wet = extra_mix_pcm16le($pcm48, $delayed);

$outDir = extra_out_dir('mixing');
extra_write_wav_pcm16le("{$outDir}/music_echo_48k.wav", $wet, 48000, 1);
extra_write_wav_pcm16le("{$outDir}/music_dry_48k.wav", $pcm48, 48000, 1);

echo "Wrote: {$outDir}/music_dry_48k.wav" . PHP_EOL;
echo "Wrote: {$outDir}/music_echo_48k.wav" . PHP_EOL;

