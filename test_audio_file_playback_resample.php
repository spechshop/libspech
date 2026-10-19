<?php

declare(strict_types=1);

require_once __DIR__ . '/plugins/Utils/sip/PcmStreamConverter.php';
require_once __DIR__ . '/plugins/Utils/sip/MediaChannel.php';
require_once __DIR__ . '/plugins/Utils/audio/AudioFilePlayback.php';
require_once __DIR__ . '/plugins/Utils/libspech/trunkController.php';

use libspech\Audio\AudioFilePlayback;
use libspech\Rtp\PcmStreamConverter;
use libspech\Sip\trunkController;

function playbackCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function playbackTone(int $rate, int $samples, int $leftHz = 440, int $rightHz = 1300): string
{
    $pcm = '';
    for ($i = 0; $i < $samples; $i++) {
        $left = (int)(11000 * sin(2 * M_PI * $leftHz * $i / $rate));
        $right = (int)(8000 * sin(2 * M_PI * $rightHz * $i / $rate));
        $pcm .= pack('v2', $left, $right);
    }
    return $pcm;
}

function playbackToneAmplitude(array $samples, int $rate, float $frequency): float
{
    $trim = min(64, intdiv(count($samples), 4));
    $samples = array_slice($samples, $trim, $trim === 0 ? null : -$trim);
    $real = $imaginary = 0.0;
    $count = count($samples);
    foreach ($samples as $index => $sample) {
        $phase = 2 * M_PI * $frequency * $index / $rate;
        $real += $sample * cos($phase);
        $imaginary -= $sample * sin($phase);
    }
    return $count === 0 ? 0.0 : 2 * hypot($real, $imaginary) / $count;
}

function playbackContext(int $sourceRate, int $sourceChannels, int $targetRate, int $targetChannels,
    int $ptime = 20, bool $loop = false): array
{
    return [
        'codec' => 'L16',
        'frequency' => $targetRate,
        'channels' => $targetChannels,
        'config' => [],
        'chunkSize' => (int)round($sourceRate * $ptime / 1000) * $sourceChannels * 2,
        'ptime' => $ptime,
        'sharing' => false,
        'loop' => $loop,
    ];
}

function playbackReference(string $pcm, int $sourceRate, int $sourceChannels, int $targetRate,
    int $targetChannels, int $ptime): string
{
    $converter = new PcmStreamConverter($sourceRate, $sourceChannels, $targetRate, $targetChannels);
    $sourceFrameBytes = (int)round($sourceRate * $ptime / 1000) * $sourceChannels * 2;
    $output = '';
    for ($offset = 0; $offset < strlen($pcm); $offset += $sourceFrameBytes) {
        $frame = substr($pcm, $offset, $sourceFrameBytes);
        if (strlen($frame) < $sourceFrameBytes) {
            $frame = str_pad($frame, $sourceFrameBytes, "\x00");
        }
        $output .= $converter->push($frame);
    }
    return $output . $converter->finish();
}

function collectPlayback(AudioFilePlayback $playback, array $context, int $expectedFrames): array
{
    $frames = [];
    for ($i = 0; $i < $expectedFrames; $i++) {
        $frame = $playback->nextPayload($context);
        playbackCheck(is_string($frame), "Playback ended before output frame $i");
        $frames[] = $frame;
    }
    return $frames;
}

// The one-shot helper remains native for stereo and preserves both channels.
{
    $reflection = new ReflectionClass(trunkController::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('doResample');
    foreach ([[48000, 8000], [8000, 48000], [24000, 16000], [16000, 24000]] as [$sourceRate, $targetRate]) {
        $pcm = playbackTone($sourceRate, $sourceRate);
        $converted = $method->invoke($controller, $pcm, $sourceRate, $targetRate,
            ['input_channels' => 2, 'output_channels' => 2]);
        $expectedSamples = (int)round($sourceRate * $targetRate / $sourceRate);
        playbackCheck(strlen($converted) === $expectedSamples * 4,
            "One-shot stereo duration mismatch $sourceRate -> $targetRate");
        $left = [];
        $right = [];
        for ($offset = 0; $offset + 4 <= strlen($converted); $offset += 4) {
            $leftValue = unpack('v', substr($converted, $offset, 2))[1];
            $rightValue = unpack('v', substr($converted, $offset + 2, 2))[1];
            $left[] = $leftValue >= 0x8000 ? $leftValue - 0x10000 : $leftValue;
            $right[] = $rightValue >= 0x8000 ? $rightValue - 0x10000 : $rightValue;
        }
        playbackCheck(playbackToneAmplitude($left, $targetRate, 440) > playbackToneAmplitude($left, $targetRate, 1300) * 4,
            "One-shot stereo left channel was mixed/swapped $sourceRate -> $targetRate");
        playbackCheck(playbackToneAmplitude($right, $targetRate, 1300) > playbackToneAmplitude($right, $targetRate, 440) * 4,
            "One-shot stereo right channel was mixed/swapped $sourceRate -> $targetRate");
    }
    playbackCheck($method->invoke($controller, '', 48000, 8000,
        ['input_channels' => 2, 'output_channels' => 2]) === '', 'Empty stereo input was not preserved');
    $identity = "\x34\x12\xcd\xab";
    playbackCheck($method->invoke($controller, $identity, 48000, 48000,
        ['input_channels' => 2, 'output_channels' => 2]) === $identity, 'Same-rate stereo changed bytes');
    $mono = '';
    for ($i = 0; $i < 8000; $i++) {
        $mono .= pack('v', (int)(10000 * sin(2 * M_PI * 500 * $i / 8000)));
    }
    $monoUp = $method->invoke($controller, $mono, 8000, 48000,
        ['input_channels' => 1, 'output_channels' => 1]);
    playbackCheck(strlen($monoUp) === 48000 * 2 && trim($monoUp, "\x00") !== '',
        'Mono 8 -> 48 kHz conversion lost signal or duration');
    $withTrailingByte = $method->invoke($controller, $pcm . "\xff", 48000, 8000,
        ['input_channels' => 2, 'output_channels' => 2]);
    playbackCheck($withTrailingByte === $method->invoke($controller, $pcm, 48000, 8000,
        ['input_channels' => 2, 'output_channels' => 2]), 'Incomplete stereo tail was not discarded consistently');
    echo "PASS doResample native one-shot stereo rates/channels/identity\n";
}

foreach ([[48000, 2, 8000, 2], [8000, 2, 48000, 2], [24000, 2, 16000, 2], [16000, 1, 24000, 2]] as
    [$sourceRate, $sourceChannels, $targetRate, $targetChannels]) {
    $ptime = 20;
    $seconds = 3;
    $sourceSamples = $sourceRate * $seconds;
    $pcm = $sourceChannels === 2
        ? playbackTone($sourceRate, $sourceSamples)
        : substr(playbackTone($sourceRate, $sourceSamples), 0, $sourceSamples * 2);
    $context = playbackContext($sourceRate, $sourceChannels, $targetRate, $targetChannels, $ptime);
    $targetFrameBytes = (int)round($targetRate * $ptime / 1000) * $targetChannels * 2;
    $playback = new AudioFilePlayback(
        $pcm,
        ['rate' => $sourceRate, 'numChannels' => $sourceChannels, 'bitDepth' => 16],
        "fixture:$sourceRate:$sourceChannels:$targetRate:$targetChannels",
        static function (string $frame, array $info, array $context): string {
            $expected = (int)round($info['rate'] * $context['ptime'] / 1000) * $info['numChannels'] * 2;
            playbackCheck(strlen($frame) === $expected, 'AudioFilePlayback passed incomplete PCM to preparation');
            return $frame;
        }
    );
    $frames = collectPlayback($playback, $context, intdiv($seconds * 1000, $ptime));
    foreach ($frames as $index => $frame) {
        playbackCheck(strlen($frame) === $targetFrameBytes,
            "Wrong RTP PCM frame size at $sourceRate -> $targetRate frame $index");
    }
    $actual = implode('', $frames);
    $expected = playbackReference($pcm, $sourceRate, $sourceChannels, $targetRate, $targetChannels, $ptime);
    playbackCheck($actual === $expected,
        "Playback packetization changed the continuous resampler output $sourceRate -> $targetRate");
    echo "PASS playback frames/duration/continuity $sourceRate/$sourceChannels -> $targetRate/$targetChannels\n";
}

// Pause keeps the already prepared packet pending; resume consumes that exact packet.
{
    $sourceRate = 48000;
    $targetRate = 8000;
    $pcm = playbackTone($sourceRate, $sourceRate);
    $context = playbackContext($sourceRate, 2, $targetRate, 2);
    $playback = new AudioFilePlayback($pcm,
        ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'pause-resume',
        static fn(string $frame): string => $frame);
    $paused = $playback->nextPayload($context, static fn(): bool => false);
    playbackCheck($paused === null, 'Paused playback emitted a packet');
    $resumed = $playback->nextPayload($context, static fn(): bool => true);
    playbackCheck(is_string($resumed) && strlen($resumed) === 640, 'Resume lost the pending complete packet');
    echo "PASS pause/resume preserves pending PCM packet\n";
}

// Simultaneous playback objects keep their FIR histories isolated.
{
    $sourceRate = 48000;
    $targetRate = 8000;
    $pcmA = playbackTone($sourceRate, $sourceRate, 440, 1300);
    $pcmB = playbackTone($sourceRate, $sourceRate, 700, 1700);
    $context = playbackContext($sourceRate, 2, $targetRate, 2);
    $prepare = static fn(string $frame): string => $frame;
    $playbackA = new AudioFilePlayback($pcmA,
        ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'session-a', $prepare);
    $playbackB = new AudioFilePlayback($pcmB,
        ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'session-b', $prepare);
    $actualA = $actualB = '';
    for ($i = 0; $i < 50; $i++) {
        $actualA .= $playbackA->nextPayload($context);
        $actualB .= $playbackB->nextPayload($context);
    }
    $expectedA = substr(playbackReference($pcmA, $sourceRate, 2, $targetRate, 2, 20), 0, 50 * 640);
    $expectedB = substr(playbackReference($pcmB, $sourceRate, 2, $targetRate, 2, 20), 0, 50 * 640);
    playbackCheck($actualA === $expectedA && $actualB === $expectedB,
        'Concurrent AudioFilePlayback objects shared conversion state');
    echo "PASS simultaneous playback sessions remain isolated\n";
}

// A ptime change repacketizes pending PCM without resetting FIR phase or dropping lookahead.
{
    $sourceRate = 48000;
    $targetRate = 8000;
    $pcm = playbackTone($sourceRate, $sourceRate * 2);
    $playback = new AudioFilePlayback($pcm,
        ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'ptime-change',
        static fn(string $frame): string => $frame);
    $first = $playback->nextPayload(playbackContext($sourceRate, 2, $targetRate, 2, 20));
    $second = $playback->nextPayload(playbackContext($sourceRate, 2, $targetRate, 2, 40));
    $third = $playback->nextPayload(playbackContext($sourceRate, 2, $targetRate, 2, 40));
    $reference = playbackReference($pcm, $sourceRate, 2, $targetRate, 2, 20);
    playbackCheck(strlen((string)$first) === 640 && strlen((string)$second) === 1280
        && strlen((string)$third) === 1280, 'ptime reconfiguration returned incomplete RTP frames');
    playbackCheck($second === substr($reference, 640, 1280)
        && $third === substr($reference, 1920, 1280),
        'ptime reconfiguration reset FIR phase or discarded anticipated PCM');
    echo "PASS ptime change preserves converter history and buffered samples\n";
}

// Async lookahead queued under the old ptime is restored as raw PCM before repacketization.
{
    \Co\run(function (): void {
        $sourceRate = 48000;
        $targetRate = 8000;
        $pcm = playbackTone($sourceRate, $sourceRate * 3);
        $playback = new AudioFilePlayback($pcm,
            ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'async-ptime-change',
            static fn(string $frame): string => $frame);
        $reference = playbackReference($pcm, $sourceRate, 2, $targetRate, 2, 20);
        try {
            $first = $playback->nextPayload(playbackContext($sourceRate, 2, $targetRate, 2, 20));
            $second = $playback->nextPayload(playbackContext($sourceRate, 2, $targetRate, 2, 40));
            playbackCheck(strlen((string)$first) === 640 && strlen((string)$second) === 1280,
                'Async ptime change emitted an incomplete packet');
            playbackCheck($first === substr($reference, 0, 640)
                && $second === substr($reference, 640, 1280),
                'Async ptime change dropped queued lookahead PCM');
        } finally {
            $playback->close();
        }
    });
    echo "PASS async ptime change preserves queued lookahead PCM\n";
}

// A loop keeps one filter stream alive across the file boundary.
{
    $sourceRate = 48000;
    $targetRate = 8000;
    $samplesPerLoop = $sourceRate * 2 / 25; // 80 ms; both test tones end at a zero crossing.
    $pcm = playbackTone($sourceRate, (int)$samplesPerLoop, 400, 1250);
    $context = playbackContext($sourceRate, 2, $targetRate, 2, 20, true);
    $playback = new AudioFilePlayback($pcm,
        ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'loop',
        static fn(string $frame): string => $frame);
    $frames = collectPlayback($playback, $context, 8);
    foreach ($frames as $frame) {
        playbackCheck(strlen($frame) === 640, 'Loop emitted an incomplete RTP frame');
        playbackCheck(trim($frame, "\x00") !== '', 'Loop inserted a silent frame at the boundary');
    }
    echo "PASS loop keeps complete frames and converter history\n";
}

// Closing an interrupted playback drops lookahead and never emits a final tail packet.
{
    $context = playbackContext(48000, 2, 8000, 2);
    $playback = new AudioFilePlayback(playbackTone(48000, 48000),
        ['rate' => 48000, 'numChannels' => 2, 'bitDepth' => 16], 'interrupt',
        static fn(string $frame): string => $frame);
    $first = $playback->nextPayload($context);
    playbackCheck(is_string($first) && strlen($first) === 640, 'Interrupted playback did not start with a full frame');
    $playback->close();
    playbackCheck($playback->nextPayload($context) === null, 'Closed playback emitted buffered lookahead');
    echo "PASS interruption discards lookahead without tail output\n";
}

// Exercise the bounded coroutine producer/consumer path used during RTP playback.
{
    $received = 0;
    \Co\run(function () use (&$received): void {
        $sourceRate = 48000;
        $context = playbackContext($sourceRate, 2, 8000, 2, 20, true);
        $playback = new AudioFilePlayback(playbackTone($sourceRate, $sourceRate),
            ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'coroutine-queue',
            static fn(string $frame): string => $frame);
        try {
            for ($i = 0; $i < 20; $i++) {
                $frame = $playback->nextPayload($context);
                playbackCheck(is_string($frame) && strlen($frame) === 640,
                    "Coroutine producer emitted an incomplete frame at $i");
                $received++;
            }
        } finally {
            $playback->close();
        }
    });
    playbackCheck($received === 20, 'Coroutine playback did not consume all complete RTP frames');
    echo "PASS coroutine producer yields bounded complete RTP frames\n";
}

// A short final source block is zero-padded once and still produces complete RTP frames.
{
    $sourceRate = 48000;
    $targetRate = 8000;
    $ptime = 20;
    $sourceSamples = (int)($sourceRate * 0.07);
    $pcm = playbackTone($sourceRate, $sourceSamples);
    $context = playbackContext($sourceRate, 2, $targetRate, 2, $ptime);
    $playback = new AudioFilePlayback($pcm,
        ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'partial-final',
        static fn(string $frame): string => $frame);
    $frames = collectPlayback($playback, $context, 4);
    foreach ($frames as $frame) {
        playbackCheck(strlen($frame) === 640, 'Final partial source produced incomplete RTP PCM');
    }
    echo "PASS final partial source block is padded only at stream end\n";
}

// Long looping playback keeps its packetizer storage bounded over thousands of frames.
{
    $sourceRate = 48000;
    $context = playbackContext($sourceRate, 2, 8000, 2, 20, true);
    $singlePacket = playbackTone($sourceRate, intdiv($sourceRate, 50), 400, 1250);
    $playback = new AudioFilePlayback($singlePacket,
        ['rate' => $sourceRate, 'numChannels' => 2, 'bitDepth' => 16], 'stress-loop',
        static fn(string $frame): string => $frame);
    $bufferProperty = new ReflectionProperty(AudioFilePlayback::class, 'convertedPcm');
    $maxLength = $maxCapacity = 0;
    for ($i = 0; $i < 3000; $i++) {
        $frame = $playback->nextPayload($context);
        playbackCheck(is_string($frame) && strlen($frame) === 640,
            "Long playback emitted an incomplete frame at $i");
        $buffer = $bufferProperty->getValue($playback);
        $maxLength = max($maxLength, $buffer->length());
        $maxCapacity = max($maxCapacity, $buffer->capacity());
    }
    playbackCheck($maxLength <= 640 * 3, 'Converted PCM accumulation exceeded its bound');
    playbackCheck($maxCapacity <= 4096, 'Small-frame converted buffer grew without bound');
    $playback->close();
    echo "PASS 3000 playback RTP frames keep output buffer bounded\n";
}
