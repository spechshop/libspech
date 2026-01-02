# Extra Audio Tools - Usage Examples

This document shows practical examples based on `demo.php` and `demo_real_audio.php`.
All examples assume PCM 16-bit signed little-endian input unless noted.

## Quick start (real audio demo)

This demo uses `mixed.pcm` (8 kHz, mono) and generates multiple WAV outputs.

```bash
php demo_real_audio.php
```

Outputs:

- `0_original.wav`
- `1_from_pcma.wav`
- `2_from_pcmu.wav`
- `3_mixed_3channels.wav`
- `4_pipeline_result.wav`

Intermediate files:

- `mixed.pcma`, `mixed.pcmu`, `mixed.l16`
- `*.pcm` (raw PCM outputs)

## Voice clarity + spatial stereo pipeline

This example uses the same flow from `demo.php`:

```php
$inputPcm = 'mixed.pcm';
$sampleInRate = 8000;  // input is 8 kHz mono

$mixed = file_get_contents($inputPcm);
$opus = new opusChannel(48000, 1);

$frames = str_split($mixed, 320); // 20 ms @ 8 kHz
$pcm_resample_48k_mono = '';
$pcm_voice_48k_mono = '';
$pcm_spatial_48k_stereo = '';
$pcm_pipeline_full_48k = '';

foreach ($frames as $pcm8) {
    if ($pcm8 === '') {
        continue;
    }

    // 1) Upsample 8 kHz mono -> 48 kHz mono
    $pcm48_mono = resampler($pcm8, $sampleInRate, 48000);

    // 2) Voice clarity (48 kHz mono)
    $pcm48_voice = $opus->enhanceVoiceClarity($pcm48_mono, 1.3);

    // 3) Spatial stereo from mono (48 kHz stereo)
    $pcm48_stereo = $opus->spatialStereoEnhance($pcm48_mono, 1.6, 0.7);

    // 4) Full pipeline (voice clarity + spatial stereo)
    $pcm48_voice_then_spatial = $opus->spatialStereoEnhance($pcm48_voice, 1.4, 0.6);

    $pcm_resample_48k_mono .= $pcm48_mono;
    $pcm_voice_48k_mono .= $pcm48_voice;
    $pcm_spatial_48k_stereo .= $pcm48_stereo;
    $pcm_pipeline_full_48k .= $pcm48_voice_then_spatial;
}
```

Recommended output WAVs (see `demo.php` for a ready function):

- `original_8k_mono.wav`
- `resample_48k_mono.wav`
- `voice_clarity_48k_mono.wav`
- `spatial_48k_stereo.wav`
- `pipeline_full_48k_stereo.wav`

## PCM <-> PCMA / PCMU

Encode and decode using A-law (PCMA) and u-law (PCMU):

```php
$pcm_original = file_get_contents('mixed.pcm');

// PCMA
$pcma = encodePcmToPcma($pcm_original);
$pcm_from_pcma = decodePcmaToPcm($pcma);

// PCMU
$pcmu = encodePcmToPcmu($pcm_original);
$pcm_from_pcmu = decodePcmuToPcm($pcmu);
```

## PCM <-> L16 (big-endian)

L16 is PCM big-endian, useful for RTP payloads:

```php
$l16 = encodePcmToL16($pcm_original);
$pcm_from_l16 = decodeL16ToPcm($l16);
```

## Mix multiple channels

Mix several PCM buffers with normalization:

```php
$channel1 = substr($pcm_original, 0, 32000);
$channel2 = substr($pcm_original, 32000, 32000);
$channel3 = substr($pcm_original, 64000);

$mixed = mixAudioChannels([$channel1, $channel2, $channel3], 8000);
```

## WAV helper (PCM -> WAV)

Both demos include a WAV helper. The simplified interface:

```php
file_put_contents('out.wav', pcmToWave($pcm_buffer, 8000, 1));
```

## Notes

- Input is expected as raw PCM 16-bit signed little-endian.
- The real demo uses 8 kHz mono (`mixed.pcm`).
- For stereo output, make sure the WAV header uses `channels = 2`.
