<?php

namespace libspech\Audio;

use Closure;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * One bounded preparer per playback, never one coroutine per packet.
 *
 * Stateless codecs queue payloads. Stateful codecs queue converted PCM: their
 * encoder advances only when a frame is consumed. Thus an immediate ptime
 * change can discard lookahead without advancing the encoder over unsent audio.
 */
final class AudioFilePlayback
{
    private const LOOKAHEAD_FRAMES = 8;
    private const CACHE_FRAMES = 256;
    private const CACHE_BYTES = 1_048_576;

    private ?Channel $queue = null;
    private array $context = [];
    private array $frames = [];
    private ?array $pending = null;
    private ?string $silenceFrame = null;
    private int $position = 0;
    private int $generation = 0;
    private bool $closed = false;
    private mixed $encoder = null;
    private array $encoderContext = [];

    public function __construct(
        private string $audioData,
        private array $info,
        private string $fileIdentity,
        private Closure $prepare
    ) {
    }

    public function nextPayload(array $context, ?Closure $canConsume = null): ?string
    {
        if ($this->closed) {
            return null;
        }
        if ($context !== $this->context) {
            $this->generation++;
            $this->queue?->close();
            $this->queue = null;
            $this->frames = [];
            $this->pending = null;
            $this->silenceFrame = null;
            $encoderContext = [$context['codec'], $context['frequency'], $context['channels'], $context['config']];
            if ($encoderContext !== $this->encoderContext) {
                $this->encoder = match ($context['codec']) {
                    'G729' => new \bcg729Channel(),
                    'GSM' => new \gsmChannel(),
                    'OPUS' => new \opusChannel(48000, $context['channels']),
                    default => null,
                };
                $this->encoderContext = $encoderContext;
            }
            $this->context = $context;
        }
        if ($this->queue === null && $this->frames === [] && Coroutine::getCid() >= 0) {
            $this->startPreparer($context);
        }
        if ($this->frames !== [] && $this->queue !== null) {
            // A complete bounded stateless cache needs neither a queue nor a
            // producer wakeup on each subsequent packet (including loops).
            $this->generation++;
            $this->queue->close();
            $this->queue = null;
        }

        $generation = $this->generation;
        if ($this->pending !== null) {
            $frame = $this->pending;
        } elseif ($this->queue !== null) {
            $frame = $this->queue->pop();
            if ($frame instanceof Throwable) {
                $this->close();
                throw $frame;
            }
            if ($frame === false || $this->closed || $generation !== $this->generation) {
                return null;
            }
        } else {
            // Preserve direct synchronous callback use outside Swoole::run().
            $frame = $this->prepareAt($this->position, $context);
        }

        if ($canConsume !== null && !$canConsume($context, $this)) {
            $this->pending = $frame;
            return null;
        }
        $this->pending = null;

        [$data, $nextPosition] = $frame;
        if ($this->encoder !== null) {
            $payload = $this->encoder->encode($data);
            if (!is_string($payload) || $payload === '') {
                $this->close();
                throw new \RuntimeException('Audio file encoder returned an empty payload');
            }
            if ($context['codec'] === 'GSM' && strlen($payload) !== intdiv(strlen($data), 320) * 33) {
                $this->close();
                throw new \RuntimeException('Invalid GSM audio file payload');
            }
        } else {
            $payload = $data;
        }
        $this->position = $nextPosition;
        return $payload;
    }

    private function startPreparer(array $context): void
    {
        // Bound both frame count and approximate PCM/payload bytes. A single
        // frame is the minimum allocation even for an unusually large ptime.
        $frameBytes = max($context['chunkSize'], (int)ceil(48000 * $context['ptime'] / 1000) * $context['channels'] * 2);
        $queue = new Channel(max(1, min(self::LOOKAHEAD_FRAMES, intdiv(262144, max(1, $frameBytes)))));
        $this->queue = $queue;
        $generation = $this->generation;
        $position = $this->position;
        Coroutine::create(function () use ($queue, $generation, $context, $position): void {
            try {
                $cacheable = $this->encoder === null
                    && (int)ceil(strlen($this->audioData) / $context['chunkSize']) <= self::CACHE_FRAMES;
                $cacheContext = $context;
                unset($cacheContext['endpoint'], $cacheContext['loop'], $cacheContext['sharing']);
                $key = $cacheable ? hash('sha256', $this->fileIdentity . serialize($cacheContext)) : null;
                if ($key !== null && $context['sharing']) {
                    try {
                        $cached = AudioCache::getEncoded($key);
                        if (!empty($cached['complete'])
                            && ($position % $context['chunkSize'] === 0 || $position >= strlen($this->audioData))) {
                            $this->frames = $cached['chunks'];
                            AudioCache::touchEncoded($key);
                        }
                    } catch (Throwable) {
                        // A cache failure must never interrupt media.
                    }
                }
                $building = [];
                $buildingBytes = 0;
                $startPosition = $position;
                while (!$this->closed && $generation === $this->generation) {
                    $frame = $this->prepareAt($position, $context);
                    if ($this->closed || $generation !== $this->generation) {
                        return;
                    }
                    $index = intdiv($position, $context['chunkSize']);
                    if ($key !== null && $this->frames === [] && $startPosition === 0) {
                        $building[$index] = $frame[0];
                        $buildingBytes += strlen($frame[0]);
                        if ($buildingBytes > self::CACHE_BYTES) {
                            $building = [];
                            $key = null;
                        } elseif ($position < strlen($this->audioData) && $frame[1] >= strlen($this->audioData)) {
                            $this->frames = $building;
                            if ($context['sharing']) {
                                try {
                                    AudioCache::setEncoded($key, ['chunks' => $building, 'complete' => true, 'frameCount' => count($building)]);
                                } catch (Throwable) {
                                }
                            }
                            $building = [];
                        }
                    }
                    if (!$queue->push($frame)) {
                        return;
                    }
                    $position = $frame[1];
                    // Yield after each bounded piece of conversion. The full
                    // queue blocks on push; there is no empty-queue polling.
                    Coroutine::sleep(.001);
                }
            } catch (Throwable $error) {
                if (!$this->closed && $generation === $this->generation) {
                    $queue->push($error);
                }
            }
        });
    }

    private function prepareAt(int $position, array $context): array
    {
        $length = strlen($this->audioData);
        if ($position >= $length && $context['loop'] && $length > 0) {
            $position = 0;
        }
        $chunkSize = $context['chunkSize'];
        if ($position >= $length && $this->silenceFrame !== null) {
            return [$this->silenceFrame, $length];
        }
        $aligned = ($position % $chunkSize) === 0;
        $index = intdiv($position, $chunkSize);
        $data = $aligned && $position < $length ? ($this->frames[$index] ?? null) : null;
        if ($data === null) {
            $pcm = str_pad(substr($this->audioData, $position, $chunkSize), $chunkSize, "\x00");
            $data = ($this->prepare)($pcm, $this->info, $context);
            if (!is_string($data) || $data === '') {
                throw new \RuntimeException('Audio file preparation returned an empty frame');
            }
            if ($position >= $length) {
                $this->silenceFrame = $data;
            }
        }
        return [$data, min($length, $position + $chunkSize)];
    }

    public function close(): void
    {
        $this->closed = true;
        $this->generation++;
        $this->queue?->close();
        $this->queue = null;
        $this->frames = [];
        $this->pending = null;
        $this->silenceFrame = null;
        $this->encoder = null;
        $this->audioData = '';
    }
}
