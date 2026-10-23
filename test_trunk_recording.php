<?php

declare(strict_types=1);

// Isolated PCM fixtures only: no controller constructor, sockets or RTP/SIP loop.
require_once __DIR__ . '/plugins/Utils/sip/MediaChannel.php';
require_once __DIR__ . '/plugins/Utils/sip/sip.php';
require_once __DIR__ . '/plugins/Utils/libspech/trunkController.php';

use libspech\Sip\trunkController;
use Swoole\Coroutine;

function recordingCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function recordingController(): trunkController
{
    return (new ReflectionClass(trunkController::class))->newInstanceWithoutConstructor();
}

function recordingAppend(trunkController $controller, int $ssrc, int $rate, int $channels, string $pcm): void
{
    // Exercise the lazy initialization used by the recording callback without
    // starting receiveMedia(), a codec decoder or any transport.
    $append = Closure::bind(function () use ($ssrc, $rate, $channels, $pcm): void {
        $this->bufferWriteSound[$ssrc] ??= [];
        $this->bufferWriteSound[$ssrc][$rate] ??= [];
        $this->bufferWriteSound[$ssrc][$rate][$channels] ??= $this->beginRecordingBuffer();
        $this->bufferWriteSound[$ssrc][$rate][$channels] .= $pcm;
    }, $controller, trunkController::class);
    $append();
}

function recordingWav(trunkController $controller, string $pcm, int $rate, int $channels): void
{
    $path = tempnam(sys_get_temp_dir(), 'libspech-recording-');
    try {
        $controller->saveBufferToWavFile($path, $pcm);
        $wav = file_get_contents($path);
        $header = unpack('VriffSize/x14/vchannels/Vrate/VbyteRate/vblockAlign/vbits/x4/Vsize', substr($wav, 4, 40));
        recordingCheck($header['channels'] === $channels, 'WAV channel count does not match PCM');
        recordingCheck($header['rate'] === $rate, 'WAV sample rate does not match PCM');
        recordingCheck($header['byteRate'] === $rate * $channels * 2, 'Incorrect WAV byte rate');
        recordingCheck($header['blockAlign'] === $channels * 2 && $header['bits'] === 16, 'Incorrect PCM16 frame format');
        recordingCheck($header['size'] === strlen($pcm) && $header['riffSize'] === strlen($pcm) + 36, 'Incorrect WAV size');
        recordingCheck(substr($wav, 44) === $pcm, 'Saving WAV changed PCM samples');
    } finally {
        unlink($path);
    }
}

Coroutine\run(function (): void {
    $frame = str_repeat(pack('v', 8156), 160); // One 20 ms PCMA-decoded mono frame.
    $other = str_repeat(pack('v', -4321 & 0xffff), 160);
    $main = str_repeat(pack('v', 8156), 967520); // 120.94 s, deliberately longer than 120 s.

    // Existing/public buffers lack chronology: use duration, never buffer count
    // or byte size, to select the principal stream. Do not pad or interleave it.
    $controller = recordingController();
    $controller->bufferWriteSound = [38 => [8000 => [1 => $frame]], 5 => [8000 => [1 => $main]]];
    recordingCheck((string)$controller->getBuffer() === $main, 'Short stray SSRC doubled or changed the principal PCM');
    recordingWav($controller, $main, 8000, 1);

    $controller->clearAudioBuffer();
    $controller->bufferWriteSound = [1 => [48000 => [2 => str_repeat($frame, 12)]], 2 => [8000 => [1 => str_repeat($other, 5)]], 3 => [8000 => [1 => '']]];
    recordingCheck((string)$controller->getBuffer() === str_repeat($other, 5), 'Principal stream was selected by bytes instead of duration');

    // A short new SSRC appearing while the principal stream continues cannot
    // extend the recording or insert zeros. Repeated reads are non-destructive.
    $controller = recordingController();
    recordingAppend($controller, 1, 8000, 1, $frame);
    recordingAppend($controller, 2, 8000, 1, $other);
    recordingAppend($controller, 1, 8000, 1, substr($main, strlen($frame)));
    recordingCheck((string)$controller->getBuffer() === $main, 'Overlapping short SSRC altered the principal stream');
    recordingCheck((string)$controller->getBuffer() === $main, 'getBuffer consumed recording state');
    recordingCheck($controller->getBufferWriteSoundBySsrc(2)[8000][1] === $other, 'Source buffers were discarded');
    $checkpoints = (new ReflectionClass($controller))->getProperty('recordingCheckpoints')->getValue($controller);
    recordingCheck(count($checkpoints) === 1, 'Metadata was added for an existing buffer');

    // Known sequential changes retain every segment, including a short first
    // stream, SSRC zero, a third stream and a new format on the same SSRC.
    $controller = recordingController();
    recordingAppend($controller, 0, 8000, 1, $frame);
    recordingAppend($controller, 2, 8000, 1, $other);
    recordingAppend($controller, 3, 8000, 1, $frame . $other);
    recordingCheck((string)$controller->getBuffer() === $frame . $other . $frame . $other, 'Sequential SSRC segments were lost/reordered');
    $controller->clearAudioBuffer();
    recordingAppend($controller, 38, 8000, 1, $frame);
    recordingAppend($controller, 5, 8000, 1, $main);
    recordingCheck((string)$controller->getBuffer() === $frame . $main, 'Short legitimate prelude was discarded');

    // Opus-style decoded PCM is already stereo interleaved. Neither one nor
    // multiple SSRCs may interleave it a second time; the bucket defines format.
    $stereo = str_repeat(pack('v2', 12000, -6000 & 0xffff), 960);
    $controller = recordingController();
    $controller->frequencyCall = 8000;
    recordingAppend($controller, 11, 48000, 2, $stereo);
    recordingCheck((string)$controller->getBuffer() === $stereo, 'Already interleaved stereo was modified');
    recordingWav($controller, $stereo, 48000, 2);
    recordingAppend($controller, 12, 48000, 2, $stereo);
    recordingCheck((string)$controller->getBuffer() === $stereo . $stereo, 'Stereo SSRC change re-interleaved PCM');
    recordingWav($controller, $stereo . $stereo, 48000, 2);

    $controller = recordingController();
    $controller->stereoMode = true;
    recordingAppend($controller, 1, 8000, 1, $frame);
    recordingCheck((string)$controller->getBuffer() === $frame, 'Stereo preference changed actual mono PCM');
    recordingWav($controller, $frame, 8000, 1);

    // Normalize only during final assembly when sequential segments differ in
    // format. The principal stream determines the single WAV output format.
    $controller = recordingController();
    $stereo8k = str_repeat(pack('v2', 12000, 6000), 320);
    recordingAppend($controller, 1, 8000, 1, $frame);
    recordingAppend($controller, 1, 8000, 2, $stereo8k);
    $assembled = (string)$controller->getBuffer();
    recordingCheck($assembled === monoToStereo($frame) . $stereo8k, 'Channel change lost samples or mislabeled the output');
    recordingWav($controller, $assembled, 8000, 2);

    $controller = recordingController();
    $mono16k = str_repeat(pack('v', 4321), 640);
    recordingAppend($controller, 1, 8000, 1, $frame);
    recordingAppend($controller, 1, 16000, 1, $mono16k);
    $assembled = (string)$controller->getBuffer();
    recordingCheck(strlen($assembled) === (320 + 640) * 2, 'Rate change altered recording duration');
    recordingCheck(substr($assembled, -strlen($mono16k)) === $mono16k, 'Rate change modified the matching-format segment');
    recordingWav($controller, $assembled, 16000, 1);

    // Saving without getBuffer still uses the available PCM bucket format.
    $controller = recordingController();
    $controller->bufferWriteSound = [1 => [48000 => [2 => $stereo]]];
    recordingWav($controller, $stereo, 48000, 2);
    $controller->clearAudioBuffer();
    recordingAppend($controller, 1, 8000, 1, $frame);
    recordingCheck((string)$controller->getBuffer() === $frame, 'clearAudioBuffer retained old checkpoints');
    recordingWav($controller, $frame, 8000, 1);

    $controller = recordingController();
    recordingCheck((string)$controller->getBuffer() === '', 'Empty recording should stay empty');
    recordingWav($controller, '', 8000, 1);
    echo "PASS isolated trunk recording: mono, stereo, SSRC changes, formats, WAV headers and reset\n";
});
