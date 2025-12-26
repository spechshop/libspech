<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

if (!class_exists('opusChannel')) {
    throw new RuntimeException('opusChannel class not available in this environment.');
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

$pcm48 = extra_resample_pcm16le($pcm, $srcRate, 48000);

$outDir = extra_out_dir('opus');

/**
 * Write WAV incrementally: write placeholder header, stream PCM, then finalize header.
 *
 * @return array{fh: resource, bytes: int, sampleRate: int, channels: int, path: string}
 */
function extra_open_wav_writer(string $path, int $sampleRate, int $channels): array
{
    $fh = fopen($path, 'w+b');
    if ($fh === false) {
        throw new RuntimeException("Failed to open for writing: {$path}");
    }
    fwrite($fh, str_repeat("\x00", 44));
    return ['fh' => $fh, 'bytes' => 0, 'sampleRate' => $sampleRate, 'channels' => $channels, 'path' => $path];
}

function extra_wav_write(array &$w, string $pcm): void
{
    fwrite($w['fh'], $pcm);
    $w['bytes'] += strlen($pcm);
}

function extra_close_wav_writer(array $w): void
{
    $header = \libspech\Sip\waveHead3($w['bytes'], $w['sampleRate'], $w['channels']);
    fseek($w['fh'], 0);
    fwrite($w['fh'], $header);
    fclose($w['fh']);
    echo "Wrote: {$w['path']}" . PHP_EOL;
}

$opus = new opusChannel(48000, 1);
try {
    // Process in frames to keep memory usage low (20ms @ 48k = 960 samples = 1920 bytes mono).
    $frames = str_split($pcm48, 1920);
    $wDry = extra_open_wav_writer("{$outDir}/music_dry_48k_mono.wav", 48000, 1);
    $wVoice = extra_open_wav_writer("{$outDir}/music_voice_48k_mono.wav", 48000, 1);
    $wSpatial = extra_open_wav_writer("{$outDir}/music_spatial_48k_stereo.wav", 48000, 2);
    $wPipeline = extra_open_wav_writer("{$outDir}/music_pipeline_48k_stereo.wav", 48000, 2);

    foreach ($frames as $frame) {
        if ($frame === '') {
            continue;
        }
        extra_wav_write($wDry, $frame);
        $v = $opus->enhanceVoiceClarity($frame, 1.25);
        $s = $opus->spatialStereoEnhance($frame, 1.6, 0.7);
        $p = $opus->spatialStereoEnhance($v, 1.4, 0.6);
        extra_wav_write($wVoice, $v);
        extra_wav_write($wSpatial, $s);
        extra_wav_write($wPipeline, $p);
    }
} finally {
    $opus->destroy();
}

extra_close_wav_writer($wDry);
extra_close_wav_writer($wVoice);
extra_close_wav_writer($wSpatial);
extra_close_wav_writer($wPipeline);
