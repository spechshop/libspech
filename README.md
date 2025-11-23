# libspech

[![PHP Version](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://www.php.net/)
[![Swoole](https://img.shields.io/badge/Swoole-6.0+-green.svg)](https://www.swoole.com/)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Real-time SIP/RTP VoIP library for PHP, built on Swoole coroutines. Make and receive real phone calls from PHP, stream
RTP audio, handle DTMF, and record audio.

## Overview

libspech provides:

- SIP user-agent features: registration, call setup/teardown (INVITE/200/ACK/BYE), digest auth
- RTP/RTCP media channels: receive and send audio frames
- Event-driven API with callbacks for ringing, answer, hangup, and incoming audio
- DTMF (RFC 2833) sending
- WAV recording helpers for captured PCM
- High performance async I/O via Swoole

This README reflects the repository as of 2025-11-23.

## Stack

- Language: PHP (no Composer in this repo)
- Framework/runtime: Swoole coroutines (PECL extension)
- Protocols: SIP, RTP/RTCP, SDP, DTMF (RFC 2833)
- Optional native extensions: `bcg729`, `opus`, `psampler` (see below)

## Requirements

- PHP 8.4+ (CLI)
- PECL Swoole 6.0+ enabled: `php -m | grep swoole`
- Linux/macOS recommended
- Optional codec extensions for additional payloads (see Codecs section)

Quick install (Swoole):

```bash
pecl install swoole
php -m | grep swoole
```

Optional extensions (install only if you need them):

```bash
# G.729 (optional)
git clone https://github.com/berzersks/bcg729.git && cd bcg729
phpize && ./configure && make && sudo make install
echo "extension=bcg729.so" | sudo tee -a "$(php -r 'echo php_ini_loaded_file();')"

# Opus (optional)
git clone https://github.com/berzersks/opus.git && cd opus
phpize && ./configure && make && sudo make install
echo "extension=opus.so" | sudo tee -a "$(php -r 'echo php_ini_loaded_file();')"

# psampler (optional)
git clone https://github.com/berzersks/psampler.git && cd psampler
phpize && ./configure && make && sudo make install
echo "extension=psampler.so" | sudo tee -a "$(php -r 'echo php_ini_loaded_file();')"
```

## Getting started

The repository includes a runnable example at `example.php`.

Minimal example:

```php
<?php
use libspech\Sip\trunkController;

include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    $username = 'your_username';
    $password = 'your_password';
    $domain   = 'sip.example.com';
    $host     = gethostbyname($domain);

    $phone = new trunkController($username, $password, $host, 5060);

    if (!$phone->register(2)) {
        throw new \Exception('Failed to register');
    }

    // Offer a linear PCM line in SDP (optional)
    $phone->mountLineCodecSDP('L16/8000');

    $phone->onRinging(function () {
        echo "Ringing...\n";
    });

    $phone->onAnswer(function (trunkController $phone) {
        echo "Answered. Receiving media...\n";
        $phone->receiveMedia();
        \Swoole\Coroutine::sleep(10);
        // Hang up after a while (BYE)
        // See example.php for a full BYE send using sip/renderMessages
    });

    $phone->onReceiveAudio(function ($pcmData, $peer, trunkController $phone) {
        $phone->bufferAudio .= $pcmData; // capture raw PCM
    });

    $phone->onHangup(function (trunkController $phone) {
        $phone->saveBufferToWavFile('audio.wav', $phone->bufferAudio);
        echo "Saved audio.wav\n";
    });

    $phone->call('5511999999999');
});
```

Run:

```bash
php example.php
```

## Scripts

- There is no package manager or script runner in this repository. Use the PHP CLI directly.
- Entry point for the demo is `example.php`.

## Environment variables

- No fixed environment variables are required by the library as committed.
- TODO: document any runtime configuration that should be externalized (e.g., SIP credentials, proxies, NAT/public IP).

## Project structure

```
libspech/
├── example.php
├── plugins/
│   ├── autoloader.php                 # Simple autoloader driven by configInterface.json
│   ├── configInterface.json           # Lists autoload directories
│   ├── Packet/
│   │   └── controller/
│   │       └── renderMessages.php     # SIP/SDP message rendering helpers
│   └── Utils/
│       ├── cache/
│       │   ├── cache.php
│       │   └── rpcClient.php
│       ├── cli/cli.php                # CLI helper utilities
│       ├── libspech/trunkController.php  # Main call controller (namespace libspech\\Sip)
│       ├── network/network.php
│       └── sip/
│           ├── AdaptiveBuffer.php
│           ├── DtmfEvent.php
│           ├── mediaChannel.php
│           ├── rtpChannel.php
│           ├── rtpc.php
│           ├── sip.php
│           └── trunkController.php    # Legacy/alt controller (kept for compatibility)
├── stubs/                             # IDE stubs for optional extensions
│   ├── bcg729Channel.php
│   ├── opusChannel.php
│   └── psampler.php
├── LICENSE
├── README.md
└── SECURITY.md
```

Note on namespaces: the class defined at `plugins/Utils/libspech/trunkController.php` uses the namespace `libspech\Sip`.
Use statements in code should target `libspech\Sip\trunkController` as shown in the example.

## Codecs

Supported/available payloads in the codebase:

| Codec                  | Payload Type | Sample Rate | Status   | Notes/Extension                       |
|------------------------|--------------|-------------|----------|---------------------------------------|
| PCMU (G.711 µ-law)     | 0            | 8 kHz       | Built-in | No extra extension required           |
| PCMA (G.711 A-law)     | 8            | 8 kHz       | Built-in | No extra extension required           |
| G.729                  | 18           | 8 kHz       | Optional | `bcg729` PHP extension                |
| Opus                   | 111          | 48 kHz      | Optional | `opus` PHP extension                  |
| L16 (Linear PCM)       | 96           | 8 kHz       | Built-in | `psampler` recommended for resampling |
| telephone-event (DTMF) | 101          | 8 kHz       | Built-in | RFC 2833 for DTMF signaling           |

Notes:

- Multiple codecs can be offered via SDP. Use `mountLineCodecSDP()` to adjust preferences.
- Some payload type values may vary depending on negotiation; verify with your provider.

## Usage notes

- Networking/NAT: ensure the local IP and ports the library binds to are reachable by the SIP peer. STUN/NAT traversal
  is not included. TODO: document any helper utilities or best practices for NAT environments.
- Security: this library focuses on basic SIP over UDP. TLS/SRTP are not documented here. TODO: clarify TLS/SRTP support
  status.

## Tests

- There are no automated tests in the repository at this time.
- TODO: add unit/integration tests for SIP message parsing, RTP timing, DTMF, and example call flows.

## License

This project is licensed under the MIT License. See `LICENSE` for details.

Third-party components may be under different licenses (Swoole, codec extensions). Review their LICENSE files before use
in production.
