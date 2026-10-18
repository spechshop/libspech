<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Audio\AudioFilePlayback;
use libspech\Sip\trunkController;
use Swoole\Coroutine;

function preparationCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function preparationEncoder(string $codec, int $channels = 1): mixed
{
    return match ($codec) {
        'G729' => new bcg729Channel(), 'GSM' => new gsmChannel(), 'OPUS' => new opusChannel(48000, $channels), default => null,
    };
}

function preparationSequence(string $codec, bool $sharing): void
{
    $trunk = (new ReflectionClass(trunkController::class))->newInstanceWithoutConstructor();
    $convert = new ReflectionMethod($trunk, 'prepareAudioFileFrame');
    $pcm = '';
    for ($i = 0; $i < 2400; $i++) {
        $pcm .= pack('v', (($i * 197) % 30000 - 15000) & 0xffff);
    }
    $info = ['rate' => 8000, 'numChannels' => 1, 'bitDepth' => 16];
    $context = ['endpoint' => 'test', 'codec' => $codec, 'frequency' => $codec === 'OPUS' ? 48000 : 8000,
        'channels' => 1, 'ptime' => 20, 'chunkSize' => 320, 'config' => [], 'loop' => true,
        'resampleMode' => 'resampler', 'sharing' => $sharing];
    $source = new AudioFilePlayback($pcm, $info, hash('sha256', $pcm),
        static fn($frame, $format, $ctx) => $convert->invoke($trunk, $frame, $format, $ctx));
    $reference = preparationEncoder($codec);
    $position = 0;
    // Multiple loops, immediate ptime changes, then a DTMF-like suspension.
    // Compare every payload against one uninterrupted dedicated native encoder.
    for ($frame = 0; $frame < 70; $frame++) {
        if ($frame === 4 || $frame === 29) {
            $context['ptime'] = 40;
            $context['chunkSize'] = 640;
        } elseif ($frame === 9 || $frame === 37) {
            $context['ptime'] = 20;
            $context['chunkSize'] = 320;
        }
        if ($frame === 45) {
            $context['config'] = ['test_version' => 2];
            if ($codec === 'OPUS') {
                $context['channels'] = 2;
            }
            $reference = preparationEncoder($codec, $context['channels']);
        }
        if ($frame === 55 && $codec !== 'OPUS') {
            $context['resampleMode'] = 'resample';
        }
        if ($frame === 50) {
            $context['loop'] = false;
        } elseif ($frame === 68) {
            $context['loop'] = true;
        }
        if ($position >= strlen($pcm) && $context['loop']) {
            $position = 0;
        }
        $input = str_pad(substr($pcm, $position, $context['chunkSize']), $context['chunkSize'], "\x00");
        $prepared = $convert->invoke($trunk, $input, $info, $context);
        $expected = $reference === null ? $prepared : $reference->encode($prepared);
        if ($frame === 15) {
            // An underflow/pop may yield just as DTMF starts. No sample or
            // encoder state may be consumed until the sender resumes.
            preparationCheck($source->nextPayload($context, static fn() => false) === null, 'Suspended frame was consumed');
            Coroutine::sleep(.05);
        }
        preparationCheck($source->nextPayload($context) === $expected, "$codec frame $frame differs from sequential encoding");
        $position = min(strlen($pcm), $position + $context['chunkSize']);
        Coroutine::sleep(.002);
    }
    $source->close();
    Coroutine::sleep(.003);
    preparationCheck($source->nextPayload($context) === null, 'Closed source produced audio');
    echo "$codec sharing=" . (int)$sharing . ": loops, PCM cursor, ptime, suspension and encoder sequence OK\n";
}

Swoole\Coroutine\run(static function (): void {
    $initial = Coroutine::stats()['coroutine_num'];
    foreach (['PCMA' => null, 'PCMU' => null, 'G729' => 'bcg729Channel', 'GSM' => 'gsmChannel', 'OPUS' => 'opusChannel'] as $codec => $class) {
        if ($class !== null && !class_exists($class)) {
            echo "SKIP $codec: extension unavailable\n";
            continue;
        }
        preparationSequence($codec, false);
        preparationSequence($codec, true);
    }

    // Long sources cannot accumulate an encoded file in session/global caches.
    $context = ['endpoint' => 'test', 'codec' => 'PCMA', 'frequency' => 8000, 'channels' => 1,
        'ptime' => 20, 'chunkSize' => 320, 'config' => [], 'loop' => true,
        'resampleMode' => 'resampler', 'sharing' => true];
    $prepare = static fn(string $pcm): string => encodePcmToPcma($pcm);
    $long = new AudioFilePlayback(str_repeat("\x10\x30", 8000 * 120), [], 'long-source-test', $prepare);
    $long->nextPayload($context);
    Coroutine::sleep(.03); // Allow the bounded queue to fill and its push to block.
    $queue = (new ReflectionProperty($long, 'queue'))->getValue($long);
    preparationCheck($queue->length() === 8, 'Lookahead was not bounded to eight frames');
    preparationCheck((new ReflectionProperty($long, 'frames'))->getValue($long) === [], 'Long file accumulated a full cache');
    $long->close(); // Wake the producer blocked on a full queue.
    Coroutine::sleep(.005);
    preparationCheck(Coroutine::stats()['coroutine_num'] === $initial, 'Producer pending after full-queue teardown');

    // Teardown while pop waits for conversion (not just when the queue is full).
    $slow = new AudioFilePlayback(str_repeat("\x00", 3200), [], 'slow-source-test', static function (string $pcm): string {
        Coroutine::sleep(.025);
        return encodePcmToPcma($pcm);
    });
    Coroutine::create(static function () use ($slow): void {
        Coroutine::sleep(.005);
        $slow->close();
    });
    preparationCheck($slow->nextPayload($context) === null, 'Teardown emitted pending media');
    Coroutine::sleep(.03);
    preparationCheck(Coroutine::stats()['coroutine_num'] === $initial, 'Producer pending after conversion teardown');
    echo "Bounded long-file queue and teardown during preparation: OK\n";

    $failed = new AudioFilePlayback(str_repeat("\x00", 320), [], 'failed-source',
        static function (): never { throw new RuntimeException('conversion failure'); });
    try {
        $failed->nextPayload($context);
        throw new LogicException('Conversion error was hidden');
    } catch (RuntimeException $error) {
        preparationCheck($error->getMessage() === 'conversion failure', 'Wrong conversion error');
    }
    preparationCheck($failed->nextPayload($context) === null, 'Failed source emitted media');
    Coroutine::sleep(.005);
    preparationCheck(Coroutine::stats()['coroutine_num'] === $initial, 'Producer pending after conversion failure');
    echo "Conversion failure is explicit, without silent frame loss or pending producer: OK\n";

    // A warm 40 ms cache starts at byte zero. Switching after one 20 ms
    // frame must retain byte 320 and keep conversion out of the sender.
    $shortPcm = str_repeat("\x10\x30\x20\x50", 800);
    $wideContext = $context;
    $wideContext['ptime'] = 40;
    $wideContext['chunkSize'] = 640;
    $converterCids = [];
    $convert = static function (string $pcm) use (&$converterCids): string {
        $converterCids[] = Coroutine::getCid();
        return encodePcmToPcma($pcm);
    };
    $warm = new AudioFilePlayback($shortPcm, [], 'unaligned-warm-cache-test', $convert);
    for ($i = 0; $i < 6; $i++) {
        $warm->nextPayload($wideContext);
        Coroutine::sleep(.003);
    }
    $warm->close();
    $changed = new AudioFilePlayback($shortPcm, [], 'unaligned-warm-cache-test', $convert);
    $changed->nextPayload($context);
    $payload = $changed->nextPayload($wideContext);
    preparationCheck($payload === encodePcmToPcma(substr($shortPcm, 320, 640)), 'Unaligned ptime change restarted/skipped PCM');
    preparationCheck(end($converterCids) !== Coroutine::getCid(), 'Warm cache moved unaligned conversion into sender');
    $changed->close();
    Coroutine::sleep(.005);
    preparationCheck(Coroutine::stats()['coroutine_num'] === $initial, 'Producer pending after unaligned ptime change');
    echo "Warm-cache ptime change at an unaligned PCM cursor: OK\n";

    // Same filename, same length and same mtime: a replacement must invalidate
    // the shared raw PCM as well as the prepared payloads.
    $wav = tempnam(sys_get_temp_dir(), 'libspech-replace-');
    $trunk = (new ReflectionClass(trunkController::class))->newInstanceWithoutConstructor();
    $firstPcm = str_repeat("\x10\x30", 1600);
    $secondPcm = str_repeat("\x20\x50", 1600);
    try {
        extra_write_wav_pcm16le($wav, $firstPcm, 8000, 1);
        $mtime = filemtime($wav);
        $trunk->defineAudioFile($wav);
        $property = new ReflectionProperty($trunk, 'audioPlayback');
        $first = $property->getValue($trunk);
        preparationCheck($first->nextPayload($context) === encodePcmToPcma(substr($firstPcm, 0, 320)), 'Initial file payload');
        extra_write_wav_pcm16le($wav, $secondPcm, 8000, 1);
        touch($wav, $mtime);
        $trunk->defineAudioFile($wav);
        preparationCheck($first->nextPayload($context) === null, 'Replaced source still active');
        $second = $property->getValue($trunk);
        preparationCheck($second->nextPayload($context) === encodePcmToPcma(substr($secondPcm, 0, 320)), 'Stale file payload');
        $trunk->stopAudioFile();
        Coroutine::sleep(.005);
        preparationCheck(Coroutine::stats()['coroutine_num'] === $initial, 'Pending coroutine after file replacement');
        echo "Same-mtime file replacement invalidates raw/prepared caches: OK\n";
    } finally {
        unlink($wav);
    }
});
