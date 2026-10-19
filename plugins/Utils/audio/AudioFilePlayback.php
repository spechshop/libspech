<?php

namespace libspech\Audio;

use Closure;
use libspech\Rtp\PcmStreamConverter;
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
    private ?PcmStreamConverter $pcmConverter = null;
    private ?\ByteBuffer $convertedPcm = null;
    private int $converterReadPosition = 0;
    private bool $converterFlushed = false;
    private int $converterTargetRate = 0;
    private int $converterTargetChannels = 0;
    private int $converterBufferLimit = 0;

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
            $keepPcmConverter = $this->canKeepPcmConverter($context);
            if ($keepPcmConverter) {
                $keepPcmConverter = $this->restoreQueuedPcm();
            }
            $this->queue?->close();
            $this->queue = null;
            $this->frames = [];
            $this->pending = null;
            $this->silenceFrame = null;
            if (!$keepPcmConverter) {
                $this->resetPcmConverter();
            }
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
        if ($frame[2] ?? false) {
            [$targetRate, $targetChannels] = $this->targetFormat($context);
            $targetInfo = $this->info;
            $targetInfo['rate'] = $targetRate;
            $targetInfo['numChannels'] = $targetChannels;
            $targetInfo['bitDepth'] = 16;
            $data = ($this->prepare)($data, $targetInfo, $context);
            if (!is_string($data) || $data === '') {
                throw new \RuntimeException('Audio file preparation returned an empty converted frame');
            }
        }
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
        $queueCapacity = $this->usesStatefulConversion($context)
            ? 1
            : max(1, min(self::LOOKAHEAD_FRAMES, intdiv(262144, max(1, $frameBytes))));
        $queue = new Channel($queueCapacity);
        $this->queue = $queue;
        $generation = $this->generation;
        $position = $this->position;
        Coroutine::create(function () use ($queue, $generation, $context, $position): void {
            try {
                $cacheable = $this->encoder === null
                    && !$this->usesStatefulConversion($context)
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
                    if ($this->usesStatefulConversion($context) && $queue->length() > 0) {
                        Coroutine::sleep(.001);
                        continue;
                    }
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
        if ($this->usesStatefulConversion($context)) {
            return $this->prepareConvertedAt($position, $context);
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

    private function usesStatefulConversion(array $context): bool
    {
        if (($this->info['bitDepth'] ?? $this->info['bitsPerSample'] ?? 16) !== 16) {
            return false;
        }
        [$targetRate, $targetChannels] = $this->targetFormat($context);
        return $targetRate !== (int)$this->info['rate']
            || $targetChannels !== (int)$this->info['numChannels'];
    }

    private function targetFormat(array $context): array
    {
        $codec = strtoupper($context['codec']);
        $rate = match ($codec) {
            'PCMA', 'PCMU', 'G729', 'GSM' => 8000,
            'OPUS' => 48000,
            default => (int)$context['frequency'],
        };
        return [$rate, $codec === 'GSM' ? 1 : (int)$context['channels']];
    }

    private function canKeepPcmConverter(array $context): bool
    {
        if ($this->pcmConverter === null || $this->converterFlushed
            || (bool)($context['loop'] ?? false) !== (bool)($this->context['loop'] ?? false)) {
            return false;
        }
        [$targetRate, $targetChannels] = $this->targetFormat($context);
        return $targetRate === $this->converterTargetRate
            && $targetChannels === $this->converterTargetChannels;
    }

    /** Convert sequential source blocks, then release only complete RTP PCM frames. */
    private function prepareConvertedAt(int $position, array $context): array
    {
        $length = strlen($this->audioData);
        if ($position >= $length && !$context['loop']) {
            $this->silenceFrame ??= $this->prepareSilenceFrame($context);
            return [$this->silenceFrame, $length];
        }

        [$targetRate, $targetChannels] = $this->targetFormat($context);
        $frameBytes = max(1, (int)round($targetRate * $context['ptime'] / 1000)) * $targetChannels * 2;
        $sourceChannels = (int)$this->info['numChannels'];
        $chunkSize = (int)$context['chunkSize'];
        if ($chunkSize <= 0 || $chunkSize % ($sourceChannels * 2) !== 0) {
            throw new \RuntimeException('Invalid source PCM frame size for stateful resampling');
        }
        $this->converterBufferLimit = max($this->converterBufferLimit, $frameBytes * 3);
        $bufferLimit = $this->converterBufferLimit;

        if ($this->pcmConverter === null) {
            $this->pcmConverter = new PcmStreamConverter(
                (int)$this->info['rate'], $sourceChannels, $targetRate, $targetChannels
            );
            $this->convertedPcm = new \ByteBuffer(max(4096, min($bufferLimit, $frameBytes * 2)));
            $this->converterReadPosition = $position;
            $this->converterTargetRate = $targetRate;
            $this->converterTargetChannels = $targetChannels;
            $this->converterBufferLimit = $bufferLimit;
            $this->primePcmConverter($position, $context);
        }

        if ($this->converterTargetRate !== $targetRate || $this->converterTargetChannels !== $targetChannels) {
            throw new \LogicException('PCM converter format changed without resetting playback state');
        }

        $this->fillConvertedFrame($context, $frameBytes, $bufferLimit);
        $buffer = $this->convertedPcm;
        if ($buffer === null || !$buffer->has($frameBytes)) {
            if ($buffer !== null && $buffer->length() > 0) {
                // The source duration can end between target packet boundaries.
                // Complete this final packet once, then switch to normal silence.
                $available = $buffer->length();
                $pcm = $buffer->pop($available) . str_repeat("\x00", $frameBytes - $available);
            } else {
                $this->silenceFrame ??= $this->prepareSilenceFrame($context);
                return [$this->silenceFrame, $length];
            }
        } else {
            $pcm = $buffer->pop($frameBytes);
        }

        if ($context['loop'] && $length > 0 && $position + $chunkSize >= $length) {
            $nextPosition = 0;
        } else {
            $nextPosition = min($length, $position + $chunkSize);
        }
        return [$pcm, $nextPosition, true];
    }

    private function fillConvertedFrame(array $context, int $frameBytes, int $bufferLimit): void
    {
        $buffer = $this->convertedPcm;
        $length = strlen($this->audioData);
        $chunkSize = (int)$context['chunkSize'];
        while ($buffer !== null && $buffer->length() < $frameBytes) {
            if ($this->converterReadPosition >= $length) {
                if ($context['loop'] && $length > 0) {
                    $this->converterReadPosition = 0;
                } else {
                    if (!$this->converterFlushed && $this->pcmConverter !== null) {
                        $tail = $this->pcmConverter->finish();
                        $this->appendConverted($tail, $bufferLimit);
                        $this->converterFlushed = true;
                    }
                    break;
                }
            }

            $pcm = substr($this->audioData, $this->converterReadPosition, $chunkSize);
            if (strlen($pcm) < $chunkSize) {
                $pcm = str_pad($pcm, $chunkSize, "\x00");
            }
            if ($this->pcmConverter === null) {
                throw new \LogicException('PCM converter was not initialized');
            }
            $this->appendConverted($this->pcmConverter->push($pcm), $bufferLimit);
            $this->converterReadPosition += $chunkSize;
        }
    }

    private function appendConverted(string $pcm, int $bufferLimit): void
    {
        if ($pcm === '') {
            return;
        }
        $buffer = $this->convertedPcm;
        if ($buffer === null || $buffer->length() + strlen($pcm) > $bufferLimit) {
            throw new \RuntimeException('Converted PCM lookahead exceeded its bounded buffer');
        }
        $buffer->append($pcm);
    }

    private function primePcmConverter(int $position, array $context): void
    {
        if ($this->pcmConverter === null || $position <= 0 || $this->audioData === '') {
            return;
        }
        $channels = (int)$this->info['numChannels'];
        $frameBytes = $channels * 2;
        $historyBytes = min(32 * $frameBytes, strlen($this->audioData));
        $historyBytes -= $historyBytes % $frameBytes;
        $start = max(0, $position - $historyBytes);
        $history = substr($this->audioData, $start, $position - $start);
        if ($context['loop'] && $start === 0 && $position < 32 * $frameBytes) {
            $missing = 32 * $frameBytes - strlen($history);
            $fromEnd = substr($this->audioData, -min($missing, strlen($this->audioData)));
            $fromEnd = substr($fromEnd, strlen($fromEnd) % $frameBytes);
            $history = $fromEnd . $history;
        }
        if ($history !== '') {
            // Seed only the FIR/DC history; priming output belongs before the
            // current source position and must never enter an RTP packet.
            $this->pcmConverter->push($history);
        }
    }

    private function prepareSilenceFrame(array $context): string
    {
        [$targetRate, $targetChannels] = $this->targetFormat($context);
        $silence = str_repeat("\x00", max(1, (int)round($targetRate * $context['ptime'] / 1000)) * $targetChannels * 2);
        $targetInfo = $this->info;
        $targetInfo['rate'] = $targetRate;
        $targetInfo['numChannels'] = $targetChannels;
        $targetInfo['bitDepth'] = 16;
        $data = ($this->prepare)($silence, $targetInfo, $context);
        if (!is_string($data) || $data === '') {
            throw new \RuntimeException('Audio silence frame preparation returned an empty frame');
        }
        return $data;
    }

    /** Recover unconsumed raw PCM from a canceled lookahead before repacketizing it. */
    private function restoreQueuedPcm(): bool
    {
        $queued = '';
        if ($this->pending !== null) {
            if (!($this->pending[2] ?? false)) {
                return false;
            }
            $queued .= $this->pending[0];
        }
        if ($this->queue !== null) {
            while ($this->queue->length() > 0) {
                $frame = $this->queue->pop();
                if (!is_array($frame) || !($frame[2] ?? false)) {
                    return false;
                }
                $queued .= $frame[0];
            }
        }
        if ($queued === '') {
            return true;
        }

        $buffer = $this->convertedPcm;
        if ($buffer === null) {
            return false;
        }
        $remaining = $buffer->length() > 0 ? $buffer->pop($buffer->length()) : '';
        $buffer->clear();
        if (strlen($queued) + strlen($remaining) > $this->converterBufferLimit) {
            return false;
        }
        $buffer->append($queued . $remaining);
        return true;
    }

    private function resetPcmConverter(): void
    {
        $this->pcmConverter = null;
        $this->convertedPcm = null;
        $this->converterReadPosition = 0;
        $this->converterFlushed = false;
        $this->converterTargetRate = 0;
        $this->converterTargetChannels = 0;
        $this->converterBufferLimit = 0;
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
        $this->resetPcmConverter();
    }
}
