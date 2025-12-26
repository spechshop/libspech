<?php

declare(strict_types=1);

/**
 * Common bootstrap/helpers for `extra/*` examples.
 *
 * These scripts are demos; they intentionally keep dependencies minimal and
 * rely on `plugins/autoloader.php` to load libspech functions/classes.
 */

function extra_root(): string
{
    return dirname(__DIR__);
}

function extra_bootstrap(): void
{
    $root = extra_root();
    chdir($root);
    require $root . '/plugins/autoloader.php';
}

/**
 * @return array{pcm: string, sampleRate: int, channels: int}
 */
function extra_read_wav_pcm16le(string $path): array
{
    $data = file_get_contents($path);
    if ($data === false) {
        throw new RuntimeException("Failed to read WAV: {$path}");
    }

    if (strlen($data) < 44 || substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WAVE') {
        throw new RuntimeException("Invalid WAV (missing RIFF/WAVE): {$path}");
    }

    $offset = 12;
    $audioFormat = null;
    $channels = null;
    $sampleRate = null;
    $bitsPerSample = null;
    $pcm = null;

    while ($offset + 8 <= strlen($data)) {
        $chunkId = substr($data, $offset, 4);
        $chunkSize = unpack('V', substr($data, $offset + 4, 4))[1];
        $chunkDataOffset = $offset + 8;

        if ($chunkDataOffset + $chunkSize > strlen($data)) {
            break;
        }

        if ($chunkId === 'fmt ') {
            if ($chunkSize < 16) {
                throw new RuntimeException("Invalid fmt chunk in WAV: {$path}");
            }
            $fmt = unpack('vaudioFormat/vchannels/VsampleRate/VbyteRate/vblockAlign/vbitsPerSample', substr($data, $chunkDataOffset, 16));
            $audioFormat = $fmt['audioFormat'];
            $channels = $fmt['channels'];
            $sampleRate = $fmt['sampleRate'];
            $bitsPerSample = $fmt['bitsPerSample'];
        } elseif ($chunkId === 'data') {
            $pcm = substr($data, $chunkDataOffset, $chunkSize);
        }

        $offset = $chunkDataOffset + $chunkSize + ($chunkSize % 2);
    }

    if ($audioFormat === null || $channels === null || $sampleRate === null || $bitsPerSample === null || $pcm === null) {
        throw new RuntimeException("WAV missing required chunks: {$path}");
    }

    if ($audioFormat !== 1 || $bitsPerSample !== 16) {
        throw new RuntimeException("Only PCM 16-bit WAV supported (format={$audioFormat}, bits={$bitsPerSample}): {$path}");
    }

    return [
        'pcm' => $pcm,
        'sampleRate' => (int)$sampleRate,
        'channels' => (int)$channels,
    ];
}

function extra_write_wav_pcm16le(string $path, string $pcm, int $sampleRate, int $channels): void
{
    $header = \libspech\Sip\waveHead3(strlen($pcm), $sampleRate, $channels);
    if (file_put_contents($path, $header . $pcm) === false) {
        throw new RuntimeException("Failed to write WAV: {$path}");
    }
}

function extra_out_dir(string $subdir): string
{
    $out = extra_root() . '/extra/out/' . trim($subdir, '/');
    if (!is_dir($out) && !mkdir($out, 0777, true) && !is_dir($out)) {
        throw new RuntimeException("Failed to create output directory: {$out}");
    }
    return $out;
}

/**
 * Convert stereo PCM16LE to mono by averaging L/R.
 */
function extra_downmix_to_mono(string $pcmStereo16le): string
{
    $len = strlen($pcmStereo16le);
    if ($len < 4) {
        return '';
    }

    $mono = '';
    $chunkBytes = 65536;
    $chunkBytes -= ($chunkBytes % 4);
    for ($offset = 0; $offset + 4 <= $len; $offset += $chunkBytes) {
        $chunk = substr($pcmStereo16le, $offset, min($chunkBytes, $len - $offset));
        $chunkLen = strlen($chunk);
        $chunkLen -= ($chunkLen % 4);
        if ($chunkLen <= 0) {
            continue;
        }

        $samples = unpack('s*', substr($chunk, 0, $chunkLen));
        if (!is_array($samples) || $samples === []) {
            continue;
        }

        $count = count($samples);
        for ($i = 1; $i < $count; $i += 2) {
            $l = $samples[$i];
            $r = $samples[$i + 1] ?? 0;
            $m = (int)(($l + $r) / 2);
            if ($m > 32767) $m = 32767;
            if ($m < -32768) $m = -32768;
            $mono .= pack('v', $m & 0xFFFF);
        }
    }

    return $mono;
}

function extra_scale_pcm16le(string $pcm16le, float $gain): string
{
    if ($gain === 1.0) {
        return $pcm16le;
    }

    $out = '';
    $len = strlen($pcm16le);
    $chunkBytes = 65536;
    $chunkBytes -= ($chunkBytes % 2);

    for ($offset = 0; $offset + 2 <= $len; $offset += $chunkBytes) {
        $chunk = substr($pcm16le, $offset, min($chunkBytes, $len - $offset));
        $chunkLen = strlen($chunk);
        $chunkLen -= ($chunkLen % 2);
        if ($chunkLen <= 0) {
            continue;
        }

        $samples = unpack('s*', substr($chunk, 0, $chunkLen));
        if (!is_array($samples) || $samples === []) {
            continue;
        }

        foreach ($samples as $s) {
            $v = (int)round($s * $gain);
            if ($v > 32767) $v = 32767;
            if ($v < -32768) $v = -32768;
            $out .= pack('v', $v & 0xFFFF);
        }
    }
    return $out;
}

/**
 * Add two PCM16LE buffers (mono) with clipping, supporting different lengths.
 */
function extra_mix_pcm16le(string $a16le, string $b16le): string
{
    $out = '';
    $len = max(strlen($a16le), strlen($b16le));
    $len -= ($len % 2);
    if ($len <= 0) {
        return '';
    }

    $chunkBytes = 65536;
    $chunkBytes -= ($chunkBytes % 2);

    for ($offset = 0; $offset + 2 <= $len; $offset += $chunkBytes) {
        $aChunk = substr($a16le, $offset, min($chunkBytes, max(0, strlen($a16le) - $offset)));
        $bChunk = substr($b16le, $offset, min($chunkBytes, max(0, strlen($b16le) - $offset)));

        $chunkLen = max(strlen($aChunk), strlen($bChunk));
        $chunkLen -= ($chunkLen % 2);
        if ($chunkLen <= 0) {
            continue;
        }

        $aChunk = str_pad(substr($aChunk, 0, $chunkLen), $chunkLen, "\x00");
        $bChunk = str_pad(substr($bChunk, 0, $chunkLen), $chunkLen, "\x00");

        $sa = unpack('s*', $aChunk);
        $sb = unpack('s*', $bChunk);

        $count = max(count($sa), count($sb));
        for ($i = 1; $i <= $count; $i++) {
            $v = (int)(($sa[$i] ?? 0) + ($sb[$i] ?? 0));
            if ($v > 32767) $v = 32767;
            if ($v < -32768) $v = -32768;
            $out .= pack('v', $v & 0xFFFF);
        }
    }
    return $out;
}

function extra_silence_ms(int $ms, int $sampleRate, int $channels): string
{
    $samples = (int)round(($sampleRate * $ms) / 1000);
    return str_repeat("\x00\x00", $samples * $channels);
}

function extra_resample_pcm16le(string $pcm, int $srcRate, int $dstRate): string
{
    if ($srcRate === $dstRate) {
        return $pcm;
    }

    if (function_exists('resampler')) {
        /** @var callable(string,int,int,bool=): string $fn */
        $fn = 'resampler';
        return $fn($pcm, $srcRate, $dstRate);
    }

    $opus = new opusChannel(48000, 1);
    try {
        return $opus->resample($pcm, $srcRate, $dstRate);
    } finally {
        $opus->destroy();
    }
}

function extra_snr_db(string $originalPcm16le, string $processedPcm16le, int $maxSamples = 20000): float
{
    $limitBytes = min(strlen($originalPcm16le), strlen($processedPcm16le), $maxSamples * 2);
    if ($limitBytes < 2) {
        return 0.0;
    }

    $orig = unpack('s*', substr($originalPcm16le, 0, $limitBytes));
    $proc = unpack('s*', substr($processedPcm16le, 0, $limitBytes));

    $signal = 0.0;
    $noise = 0.0;
    foreach ($orig as $i => $o) {
        $p = $proc[$i] ?? 0;
        $signal += $o * $o;
        $d = $o - $p;
        $noise += $d * $d;
    }

    if ($noise <= 0.0) {
        return INF;
    }

    return 10.0 * log10($signal / $noise);
}

/**
 * Compute SNR (dB) after searching for a small time alignment shift.
 *
 * Some transforms introduce a fixed delay; this makes a naive sample-by-sample
 * comparison look much worse than it is.
 */
function extra_snr_db_aligned(
    string $originalPcm16le,
    string $processedPcm16le,
    int $maxShiftSamples = 200,
    int $maxSamples = 20000
): float {
    $limitBytes = min(strlen($originalPcm16le), strlen($processedPcm16le), $maxSamples * 2);
    if ($limitBytes < 2) {
        return 0.0;
    }

    $orig = array_values(unpack('s*', substr($originalPcm16le, 0, $limitBytes)) ?: []);
    $proc = array_values(unpack('s*', substr($processedPcm16le, 0, $limitBytes)) ?: []);

    $n = min(count($orig), count($proc));
    if ($n < 2) {
        return 0.0;
    }

    $best = -INF;
    for ($shift = -$maxShiftSamples; $shift <= $maxShiftSamples; $shift++) {
        $signal = 0.0;
        $noise = 0.0;
        $count = 0;

        for ($i = 0; $i < $n; $i++) {
            $j = $i + $shift;
            if ($j < 0 || $j >= $n) {
                continue;
            }
            $o = $orig[$i];
            $p = $proc[$j];
            $signal += $o * $o;
            $d = $o - $p;
            $noise += $d * $d;
            $count++;
        }

        if ($count < 100 || $noise <= 0.0) {
            continue;
        }

        $snr = 10.0 * log10($signal / $noise);
        if ($snr > $best) {
            $best = $snr;
        }
    }

    return $best;
}
