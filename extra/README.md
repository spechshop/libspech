# Extra (audio demos)

This folder contains **PHP-only** “extras” that demonstrate audio utilities already available in this repository (resample, codecs, Opus helpers, mixing, quality metrics, and a simple Swoole streaming server).

## Quick start

1) Check the environment (modules, functions/classes):

```bash
php extra/tools/00_env_check.php
```

2) Generate local stubs (optional, for IDE/inspection):

```bash
php extra/tools/01_generate_stubs.php
```

3) Run any example:

```bash
php extra/resample/01_resample_music.php
```

Outputs go to `extra/out/*` (ignored).

## Layout

- `extra/assets/` - input assets (`music.wav`)
- `extra/resample/` - sample-rate conversions
- `extra/mixing/` - mix/echo/offset examples
- `extra/codecs/` - PCMA/PCMU/L16 examples + roundtrip checks
- `extra/opus_examples/` - voice clarity / spatial stereo examples
- `extra/quality/` - SNR and simple reports
- `extra/streaming/` - simple Swoole HTTP audio streaming servers (WAV and Opus)
- `extra/stubs/` - generated stubs (local)
- `extra/tools/` - environment/stub helper scripts
