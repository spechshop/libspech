<?php

declare(strict_types=1);

namespace libspech\audio;

use Closure;
use InvalidArgumentException;

final class EarlyGreetingDetector
{
    private string $frameBuffer = '';
    private string $analysisBuffer = '';

    private int $frameBytes;
    private int $analysisWindowBytes;

    private int $elapsedMs = 0;

    private bool $answered = false;
    private ?int $answeredAtMs = null;

    private bool $voiceActive = false;
    private int $voiceStartedAtMs = 0;
    private int $voiceFramesMs = 0;
    private int $voiceGapMs = 0;
    private bool $greetingReported = false;
    private bool $earlyGreetingDetected = false;

    private int $postAnswerElapsedMs = 0;
    private bool $postAnswerVoiceActive = false;
    private int $postAnswerVoiceStartedAtMs = 0;
    private int $postAnswerCurrentVoiceMs = 0;
    private int $postAnswerCurrentGapMs = 0;
    private int $postAnswerLastSpeechMs = 0;
    private int $postAnswerSilenceAfterSpeechMs = 0;
    private int $postAnswerSpeechSegments = 0;
    private int $postAnswerTotalVoiceMs = 0;
    private int $postAnswerLongestVoiceMs = 0;
    private bool $voiceCrossedAnswer = false;
    private int $earlyVoiceAtAnswerMs = 0;

    private ?string $amdResult = null;
    private ?string $amdReason = null;

    private float $noiseFloorDbfs = -65.0;

    private ?Closure $onVoiceStart = null;
    private ?Closure $onVoiceEnd = null;
    private ?Closure $onGreetingDetected = null;
    private ?Closure $onAnalysis = null;
    private ?Closure $onAnswerBoundary = null;
    private ?Closure $onHumanLikely = null;
    private ?Closure $onMachineLikely = null;
    private ?Closure $onUnknown = null;
    private ?Closure $onAmdResult = null;

    public function __construct(
        private readonly int $sampleRate = 8000,
        private readonly int $frameDurationMs = 20,
        private readonly int $analysisWindowMs = 200,
        private readonly int $minimumGreetingVoiceMs = 800,
        private readonly int $maximumInternalGapMs = 120,
        private readonly float $minimumVoiceDbfs = -42.0,
        private readonly float $noiseMarginDb = 10.0,
        private readonly float $tone425MinimumDbfs = -45.0,
        private readonly float $tone425MinimumProminenceDb = 14.0,
        private readonly int $humanMinimumSpeechMs = 200,
        private readonly int $humanMaximumSpeechMs = 1200,
        private readonly int $humanSilenceAfterSpeechMs = 600,
        private readonly int $machineGreetingVoiceMs = 2000,
        private readonly int $postAnswerAnalysisTimeoutMs = 6000,
    ) {
        if ($this->sampleRate <= 0) {
            throw new InvalidArgumentException('sampleRate deve ser maior que zero.');
        }

        if ($this->frameDurationMs <= 0) {
            throw new InvalidArgumentException('frameDurationMs deve ser maior que zero.');
        }

        if ($this->analysisWindowMs < $this->frameDurationMs) {
            throw new InvalidArgumentException(
                'analysisWindowMs deve ser maior ou igual a frameDurationMs.'
            );
        }

        if ($this->humanMinimumSpeechMs < 0) {
            throw new InvalidArgumentException('humanMinimumSpeechMs não pode ser negativo.');
        }

        if ($this->humanMaximumSpeechMs < $this->humanMinimumSpeechMs) {
            throw new InvalidArgumentException(
                'humanMaximumSpeechMs deve ser maior ou igual a humanMinimumSpeechMs.'
            );
        }

        if ($this->machineGreetingVoiceMs <= 0) {
            throw new InvalidArgumentException('machineGreetingVoiceMs deve ser maior que zero.');
        }

        if ($this->postAnswerAnalysisTimeoutMs <= 0) {
            throw new InvalidArgumentException(
                'postAnswerAnalysisTimeoutMs deve ser maior que zero.'
            );
        }

        $frameSamples = (int) round(
            $this->sampleRate * ($this->frameDurationMs / 1000)
        );

        $analysisSamples = (int) round(
            $this->sampleRate * ($this->analysisWindowMs / 1000)
        );

        // PCM16 mono: 2 bytes por amostra.
        $this->frameBytes = $frameSamples * 2;
        $this->analysisWindowBytes = $analysisSamples * 2;
    }

    public function onVoiceStart(callable $callback): self
    {
        $this->onVoiceStart = Closure::fromCallable($callback);

        return $this;
    }

    public function onVoiceEnd(callable $callback): self
    {
        $this->onVoiceEnd = Closure::fromCallable($callback);

        return $this;
    }

    public function onGreetingDetected(callable $callback): self
    {
        $this->onGreetingDetected = Closure::fromCallable($callback);

        return $this;
    }

    public function onAnalysis(callable $callback): self
    {
        $this->onAnalysis = Closure::fromCallable($callback);

        return $this;
    }

    public function onAnswerBoundary(callable $callback): self
    {
        $this->onAnswerBoundary = Closure::fromCallable($callback);

        return $this;
    }

    public function onHumanLikely(callable $callback): self
    {
        $this->onHumanLikely = Closure::fromCallable($callback);

        return $this;
    }

    public function onMachineLikely(callable $callback): self
    {
        $this->onMachineLikely = Closure::fromCallable($callback);

        return $this;
    }

    public function onUnknown(callable $callback): self
    {
        $this->onUnknown = Closure::fromCallable($callback);

        return $this;
    }

    public function onAmdResult(callable $callback): self
    {
        $this->onAmdResult = Closure::fromCallable($callback);

        return $this;
    }

    public function push(string $pcmData): void
    {
        if ($pcmData === '') {
            return;
        }

        $this->frameBuffer .= $pcmData;

        while (strlen($this->frameBuffer) >= $this->frameBytes) {
            $frame = substr($this->frameBuffer, 0, $this->frameBytes);
            $this->frameBuffer = substr($this->frameBuffer, $this->frameBytes);

            $this->processFrame($frame);
        }
    }

    public function markAnswered(): void
    {
        if ($this->answered) {
            return;
        }

        $this->answered = true;
        $this->answeredAtMs = $this->elapsedMs;
        $this->postAnswerElapsedMs = 0;
        $this->voiceCrossedAnswer = $this->voiceActive;
        $this->earlyVoiceAtAnswerMs = $this->voiceActive
            ? $this->voiceFramesMs
            : 0;

        if ($this->voiceCrossedAnswer) {
            $this->postAnswerVoiceActive = true;
            $this->postAnswerVoiceStartedAtMs = $this->elapsedMs;
            $this->postAnswerSpeechSegments = 1;
            $this->postAnswerCurrentVoiceMs = 0;
            $this->postAnswerCurrentGapMs = 0;
        }

        $event = [
            'audio_ms' => $this->elapsedMs,
            'early_voice_active' => $this->voiceActive,
            'early_voice_started_ms' => $this->voiceActive
                ? $this->voiceStartedAtMs
                : null,
            'early_voice_ms' => $this->voiceFramesMs,
            'greeting_detected' => $this->earlyGreetingDetected,
            'voice_crossed_answer' => $this->voiceCrossedAnswer,
        ];

        if ($this->onAnswerBoundary !== null) {
            ($this->onAnswerBoundary)($event);
        }
    }

    public function finish(string $reason = 'finished'): void
    {
        if (!$this->answered && $this->voiceActive) {
            $this->finishEarlyVoiceSegment($reason);
        }

        if ($this->answered && $this->postAnswerVoiceActive) {
            $this->finishPostAnswerVoiceSegment($reason);
        }

        if ($this->answered && $this->amdResult === null) {
            $this->emitAmdResult('unknown', $reason);
        }
    }

    public function isGreetingDetected(): bool
    {
        return $this->earlyGreetingDetected;
    }

    public function isAnswered(): bool
    {
        return $this->answered;
    }

    public function getAnsweredAtMs(): ?int
    {
        return $this->answeredAtMs;
    }

    public function getAmdResult(): ?string
    {
        return $this->amdResult;
    }

    public function getAmdReason(): ?string
    {
        return $this->amdReason;
    }

    private function processFrame(string $frame): void
    {
        $frameStartMs = $this->elapsedMs;
        $this->elapsedMs += $this->frameDurationMs;

        $this->analysisBuffer .= $frame;

        if (strlen($this->analysisBuffer) > $this->analysisWindowBytes) {
            $this->analysisBuffer = substr(
                $this->analysisBuffer,
                -$this->analysisWindowBytes
            );
        }

        $frameSamples = $this->decodePcm16LittleEndian($frame);

        if ($frameSamples === []) {
            return;
        }

        $rmsDbfs = $this->calculateRmsDbfs($frameSamples);

        $tone425 = [
            'detected' => false,
            'frequency_hz' => 0.0,
            'level_dbfs' => -120.0,
            'prominence_db' => 0.0,
        ];

        if (strlen($this->analysisBuffer) >= $this->analysisWindowBytes) {
            $analysisSamples = $this->decodePcm16LittleEndian(
                $this->analysisBuffer
            );

            $tone425 = $this->analyzeTone425($analysisSamples);
        }

        $voiceThresholdDbfs = max(
            $this->minimumVoiceDbfs,
            $this->noiseFloorDbfs + $this->noiseMarginDb
        );

        // O tom de ringback não deve ser contado como voz.
        $isVoice =
            $rmsDbfs >= $voiceThresholdDbfs &&
            !$tone425['detected'];

        if (
            !$isVoice &&
            !$tone425['detected'] &&
            $rmsDbfs < -40.0
        ) {
            $this->noiseFloorDbfs =
                ($this->noiseFloorDbfs * 0.98) +
                ($rmsDbfs * 0.02);
        }

        $analysis = [
            'audio_ms' => $frameStartMs,
            'phase' => $this->answered
                ? 'answered'
                : 'early_media',
            'rms_dbfs' => $rmsDbfs,
            'noise_floor_dbfs' => $this->noiseFloorDbfs,
            'voice_threshold_dbfs' => $voiceThresholdDbfs,
            'voice' => $isVoice,
            'tone_425' => $tone425,
            'amd_result' => $this->amdResult,
        ];

        if ($this->onAnalysis !== null) {
            ($this->onAnalysis)($analysis);
        }

        if (!$this->answered) {
            $this->updateEarlyVoiceState(
                isVoice: $isVoice,
                frameStartMs: $frameStartMs,
                analysis: $analysis
            );

            return;
        }

        $this->updatePostAnswerState(
            isVoice: $isVoice,
            frameStartMs: $frameStartMs,
            analysis: $analysis
        );
    }

    private function updateEarlyVoiceState(
        bool $isVoice,
        int $frameStartMs,
        array $analysis
    ): void {
        if ($isVoice) {
            if (!$this->voiceActive) {
                $this->voiceActive = true;
                $this->voiceStartedAtMs = $frameStartMs;
                $this->voiceFramesMs = 0;
                $this->voiceGapMs = 0;
                $this->greetingReported = false;

                if ($this->onVoiceStart !== null) {
                    ($this->onVoiceStart)([
                        'audio_ms' => $frameStartMs,
                        'rms_dbfs' => $analysis['rms_dbfs'],
                        'phase' => 'early_media',
                    ]);
                }
            }

            $this->voiceFramesMs += $this->frameDurationMs;
            $this->voiceGapMs = 0;

            if (
                !$this->greetingReported &&
                $this->voiceFramesMs >= $this->minimumGreetingVoiceMs
            ) {
                $this->greetingReported = true;
                $this->earlyGreetingDetected = true;

                if ($this->onGreetingDetected !== null) {
                    ($this->onGreetingDetected)([
                        'audio_ms' => $frameStartMs,
                        'started_at_ms' => $this->voiceStartedAtMs,
                        'voiced_ms' => $this->voiceFramesMs,
                        'phase' => 'early_media',
                        'classification' => 'early_greeting',
                    ]);
                }
            }

            return;
        }

        if (!$this->voiceActive) {
            return;
        }

        $this->voiceGapMs += $this->frameDurationMs;

        if ($this->voiceGapMs <= $this->maximumInternalGapMs) {
            return;
        }

        $this->finishEarlyVoiceSegment('silence');
    }

    private function finishEarlyVoiceSegment(string $reason): void
    {
        if (!$this->voiceActive) {
            return;
        }

        $event = [
            'audio_ms' => $this->elapsedMs,
            'started_at_ms' => $this->voiceStartedAtMs,
            'voiced_ms' => $this->voiceFramesMs,
            'gap_ms' => $this->voiceGapMs,
            'greeting_detected' => $this->greetingReported,
            'reason' => $reason,
            'phase' => 'early_media',
        ];

        if ($this->onVoiceEnd !== null) {
            ($this->onVoiceEnd)($event);
        }

        $this->voiceActive = false;
        $this->voiceStartedAtMs = 0;
        $this->voiceFramesMs = 0;
        $this->voiceGapMs = 0;
        $this->greetingReported = false;
    }

    private function updatePostAnswerState(
        bool $isVoice,
        int $frameStartMs,
        array $analysis
    ): void {
        if ($this->answeredAtMs === null) {
            return;
        }

        $this->postAnswerElapsedMs = max(
            0,
            $this->elapsedMs - $this->answeredAtMs
        );

        if ($this->amdResult !== null) {
            return;
        }

        if ($isVoice) {
            if (!$this->postAnswerVoiceActive) {
                $this->postAnswerVoiceActive = true;
                $this->postAnswerVoiceStartedAtMs = $frameStartMs;
                $this->postAnswerCurrentVoiceMs = 0;
                $this->postAnswerCurrentGapMs = 0;
                $this->postAnswerSilenceAfterSpeechMs = 0;
                $this->postAnswerSpeechSegments++;

                if ($this->onVoiceStart !== null) {
                    ($this->onVoiceStart)([
                        'audio_ms' => $frameStartMs,
                        'post_answer_ms' => $this->postAnswerElapsedMs,
                        'rms_dbfs' => $analysis['rms_dbfs'],
                        'phase' => 'answered',
                    ]);
                }
            }

            $this->postAnswerCurrentVoiceMs += $this->frameDurationMs;
            $this->postAnswerTotalVoiceMs += $this->frameDurationMs;
            $this->postAnswerCurrentGapMs = 0;
            $this->postAnswerSilenceAfterSpeechMs = 0;
            $this->postAnswerLongestVoiceMs = max(
                $this->postAnswerLongestVoiceMs,
                $this->postAnswerCurrentVoiceMs
            );

            $continuousVoiceMs =
                $this->postAnswerCurrentVoiceMs +
                ($this->voiceCrossedAnswer ? $this->earlyVoiceAtAnswerMs : 0);

            if ($continuousVoiceMs >= $this->machineGreetingVoiceMs) {
                $this->emitAmdResult('machine_likely', 'long_greeting');

                return;
            }
        } elseif ($this->postAnswerVoiceActive) {
            $this->postAnswerCurrentGapMs += $this->frameDurationMs;

            if ($this->postAnswerCurrentGapMs > $this->maximumInternalGapMs) {
                $this->finishPostAnswerVoiceSegment('silence');
            }
        } elseif ($this->postAnswerSpeechSegments > 0) {
            $this->postAnswerSilenceAfterSpeechMs += $this->frameDurationMs;

            if (
                !$this->voiceCrossedAnswer &&
                $this->postAnswerSpeechSegments === 1 &&
                $this->postAnswerLastSpeechMs >= $this->humanMinimumSpeechMs &&
                $this->postAnswerLastSpeechMs <= $this->humanMaximumSpeechMs &&
                $this->postAnswerSilenceAfterSpeechMs >= $this->humanSilenceAfterSpeechMs
            ) {
                $this->emitAmdResult(
                    'human_likely',
                    'short_greeting_followed_by_silence'
                );

                return;
            }
        }

        if (
            $this->amdResult === null &&
            $this->postAnswerElapsedMs >= $this->postAnswerAnalysisTimeoutMs
        ) {
            $this->emitAmdResult('unknown', 'analysis_timeout');
        }
    }

    private function finishPostAnswerVoiceSegment(string $reason): void
    {
        if (!$this->postAnswerVoiceActive) {
            return;
        }

        $this->postAnswerLastSpeechMs = $this->postAnswerCurrentVoiceMs;
        $this->postAnswerLongestVoiceMs = max(
            $this->postAnswerLongestVoiceMs,
            $this->postAnswerLastSpeechMs
        );

        $event = [
            'audio_ms' => $this->elapsedMs,
            'post_answer_ms' => $this->postAnswerElapsedMs,
            'started_at_ms' => $this->postAnswerVoiceStartedAtMs,
            'voiced_ms' => $this->postAnswerLastSpeechMs,
            'gap_ms' => $this->postAnswerCurrentGapMs,
            'speech_segments' => $this->postAnswerSpeechSegments,
            'greeting_detected' => false,
            'voice_crossed_answer' => $this->voiceCrossedAnswer,
            'reason' => $reason,
            'phase' => 'answered',
        ];

        if ($this->onVoiceEnd !== null) {
            ($this->onVoiceEnd)($event);
        }

        $this->postAnswerVoiceActive = false;
        $this->postAnswerVoiceStartedAtMs = 0;
        $this->postAnswerCurrentVoiceMs = 0;
        $this->postAnswerCurrentGapMs = 0;
        $this->postAnswerSilenceAfterSpeechMs = 0;
    }

    private function emitAmdResult(string $classification, string $reason): void
    {
        if ($this->amdResult !== null) {
            return;
        }

        $this->amdResult = $classification;
        $this->amdReason = $reason;

        $event = [
            'audio_ms' => $this->elapsedMs,
            'answered_at_ms' => $this->answeredAtMs,
            'post_answer_ms' => $this->postAnswerElapsedMs,
            'classification' => $classification,
            'reason' => $reason,
            'voice_crossed_answer' => $this->voiceCrossedAnswer,
            'early_greeting_detected' => $this->earlyGreetingDetected,
            'early_voice_at_answer_ms' => $this->earlyVoiceAtAnswerMs,
            'speech_segments' => $this->postAnswerSpeechSegments,
            'current_speech_ms' => $this->postAnswerCurrentVoiceMs,
            'last_speech_ms' => $this->postAnswerLastSpeechMs,
            'total_voice_ms' => $this->postAnswerTotalVoiceMs,
            'longest_voice_ms' => $this->postAnswerLongestVoiceMs,
            'silence_after_speech_ms' => $this->postAnswerSilenceAfterSpeechMs,
        ];

        if ($this->onAmdResult !== null) {
            ($this->onAmdResult)($event);
        }

        if ($classification === 'human_likely' && $this->onHumanLikely !== null) {
            ($this->onHumanLikely)($event);

            return;
        }

        if ($classification === 'machine_likely' && $this->onMachineLikely !== null) {
            ($this->onMachineLikely)($event);

            return;
        }

        if ($classification === 'unknown' && $this->onUnknown !== null) {
            ($this->onUnknown)($event);
        }
    }

    private function analyzeTone425(array $samples): array
    {
        if ($samples === []) {
            return [
                'detected' => false,
                'frequency_hz' => 0.0,
                'level_dbfs' => -120.0,
                'prominence_db' => 0.0,
            ];
        }

        $windowed = $this->applyHannWindow($samples);

        $bestFrequency = 425.0;
        $bestLevel = -120.0;

        for ($frequency = 395; $frequency <= 455; $frequency += 5) {
            $level = $this->goertzelDbfs(
                $windowed,
                (float) $frequency
            );

            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestFrequency = (float) $frequency;
            }
        }

        $refineStart = max(395, (int) $bestFrequency - 5);
        $refineEnd = min(455, (int) $bestFrequency + 5);

        for ($frequency = $refineStart; $frequency <= $refineEnd; $frequency++) {
            $level = $this->goertzelDbfs(
                $windowed,
                (float) $frequency
            );

            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestFrequency = (float) $frequency;
            }
        }

        $backgroundFrequencies = [
            250.0,
            300.0,
            350.0,
            500.0,
            600.0,
            700.0,
            850.0,
            1000.0,
            1200.0,
        ];

        $backgroundLevels = [];

        foreach ($backgroundFrequencies as $frequency) {
            $backgroundLevels[] = $this->goertzelDbfs(
                $windowed,
                $frequency
            );
        }

        $backgroundLevel = $this->median($backgroundLevels);
        $prominenceDb = $bestLevel - $backgroundLevel;

        $detected =
            $bestFrequency >= 395.0 &&
            $bestFrequency <= 455.0 &&
            $bestLevel >= $this->tone425MinimumDbfs &&
            $prominenceDb >= $this->tone425MinimumProminenceDb;

        return [
            'detected' => $detected,
            'frequency_hz' => $bestFrequency,
            'level_dbfs' => $bestLevel,
            'prominence_db' => $prominenceDb,
        ];
    }

    private function decodePcm16LittleEndian(string $pcm): array
    {
        if ($pcm === '' || (strlen($pcm) % 2) !== 0) {
            return [];
        }

        $values = unpack('v*', $pcm);

        if ($values === false) {
            return [];
        }

        $samples = [];

        foreach ($values as $value) {
            if ($value >= 0x8000) {
                $value -= 0x10000;
            }

            $samples[] = (float) $value;
        }

        return $samples;
    }

    private function calculateRmsDbfs(array $samples): float
    {
        if ($samples === []) {
            return -120.0;
        }

        $sumSquares = 0.0;

        foreach ($samples as $sample) {
            $sumSquares += $sample * $sample;
        }

        $rms = sqrt($sumSquares / count($samples));

        if ($rms <= 0.0) {
            return -120.0;
        }

        return max(
            -120.0,
            20.0 * log10($rms / 32768.0)
        );
    }

    private function applyHannWindow(array $samples): array
    {
        $count = count($samples);

        if ($count <= 1) {
            return $samples;
        }

        $result = [];
        $divisor = $count - 1;

        foreach ($samples as $index => $sample) {
            $coefficient = 0.5 * (
                1.0 - cos(
                    (2.0 * M_PI * $index) / $divisor
                )
            );

            $result[] = $sample * $coefficient;
        }

        return $result;
    }

    private function goertzelDbfs(array $samples, float $frequency): float
    {
        $sampleCount = count($samples);

        if ($sampleCount === 0) {
            return -120.0;
        }

        $omega = (2.0 * M_PI * $frequency) / $this->sampleRate;
        $coefficient = 2.0 * cos($omega);

        $previous = 0.0;
        $previousPrevious = 0.0;

        foreach ($samples as $sample) {
            $current =
                $sample +
                ($coefficient * $previous) -
                $previousPrevious;

            $previousPrevious = $previous;
            $previous = $current;
        }

        $power =
            ($previous * $previous) +
            ($previousPrevious * $previousPrevious) -
            ($coefficient * $previous * $previousPrevious);

        if ($power <= 0.0) {
            return -120.0;
        }

        $magnitude = sqrt($power);
        $amplitude = (4.0 * $magnitude) / $sampleCount;

        if ($amplitude <= 0.0) {
            return -120.0;
        }

        return max(
            -120.0,
            min(
                0.0,
                20.0 * log10($amplitude / 32768.0)
            )
        );
    }

    private function median(array $values): float
    {
        if ($values === []) {
            return -120.0;
        }

        sort($values, SORT_NUMERIC);

        $count = count($values);
        $middle = intdiv($count, 2);

        if (($count % 2) === 1) {
            return (float) $values[$middle];
        }

        return (
            $values[$middle - 1] +
            $values[$middle]
        ) / 2.0;
    }
}
