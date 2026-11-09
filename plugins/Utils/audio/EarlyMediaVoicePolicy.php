<?php
declare(strict_types=1);

namespace libspech\audio;

/** Confirmation of acoustic voice activity; never a mailbox classification. */
final class EarlyMediaVoicePolicy
{
    private int $voiceMs = 0;
    private int $pauseMs = 0;
    private bool $decided = false;
    private int $maximumVoiceMs = 0;
    private int $speechEpisodes = 0;

    public function __construct(private readonly int $minimumVoiceMs = 1000)
    {
        if ($minimumVoiceMs < 200 || $minimumVoiceMs > 5000 || $minimumVoiceMs % 20 !== 0) {
            throw new \InvalidArgumentException('minimumVoiceMs must be between 200 and 5000 ms, in multiples of 20 ms');
        }
    }

    public function frame(string $frameSignal, string $stableSignal): bool
    {
        if ($this->decided) return false;
        if ($frameSignal === 'voice_like' && $stableSignal === 'voice_like') {
            if ($this->voiceMs === 0) $this->speechEpisodes++;
            $this->voiceMs += 20;
            $this->maximumVoiceMs = max($this->maximumVoiceMs, $this->voiceMs);
            $this->pauseMs = 0;
        } else {
            // Native transient classifications within a syllable are gaps too.
            // Silence/tones/noise/music never count, even during speech hangover.
            $this->pauseMs += 20;
            if ($this->pauseMs > 200) $this->voiceMs = 0;
        }
        if ($this->voiceMs < $this->minimumVoiceMs) return false;
        return $this->decided = true;
    }

    public function resetEvidence(): void
    {
        $this->voiceMs = $this->pauseMs = 0;
    }

    public function voiceMs(): int { return $this->voiceMs; }

    public function metrics(): array
    {
        return ['minimum_voice_ms' => $this->minimumVoiceMs,
            'maximum_qualified_voice_ms' => $this->maximumVoiceMs,
            'speech_episodes' => $this->speechEpisodes];
    }
}
