<?php

namespace libspech\Rtp;

use PcmBuffer;

/** One continuous PCM16LE source-to-destination stream. */
final class PcmStreamConverter
{
    private ?PcmBuffer $buffer = null;
    private bool $finished = false;

    public function __construct(
        public readonly int $sourceRate,
        public readonly int $sourceChannels,
        public readonly int $targetRate,
        public readonly int $targetChannels,
    ) {
        if ($sourceRate <= 0 || $targetRate <= 0
            || !in_array($sourceChannels, [1, 2], true)
            || !in_array($targetChannels, [1, 2], true)) {
            throw new \InvalidArgumentException('Invalid PCM stream format');
        }
        // Keep the identity path allocation-free, including PcmBuffer construction.
        if ($sourceRate !== $targetRate || $sourceChannels !== $targetChannels) {
            $this->buffer = new PcmBuffer($sourceRate, $sourceChannels);
        }
    }

    public function push(string $pcm): string
    {
        if ($this->finished) {
            throw new \LogicException('PCM stream already finished');
        }
        if (strlen($pcm) % (2 * $this->sourceChannels) !== 0) {
            throw new \InvalidArgumentException('Unaligned PCM16LE frame');
        }
        if ($pcm === '' || $this->buffer === null) {
            return $pcm;
        }
        $this->buffer->clear();
        $this->buffer->append($pcm);
        if ($this->sourceChannels === 2 && $this->targetChannels === 1) {
            $this->buffer->toMono();
        } elseif ($this->sourceChannels === 1 && $this->targetChannels === 2) {
            $this->buffer->toStereo();
        }
        if ($this->sourceRate !== $this->targetRate) {
            $this->buffer->resample($this->targetRate);
        }
        return $this->buffer->toString();
    }

    public function finish(): string
    {
        if ($this->finished) {
            return '';
        }
        $this->finished = true;
        if ($this->buffer === null) {
            return '';
        }
        $this->buffer->flush();
        return $this->buffer->toString();
    }
}
