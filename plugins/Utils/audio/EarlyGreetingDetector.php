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
    private bool $earlyAnnouncementDetected = false;
    private int $earlySpeechSegments = 0;
    private int $earlyTotalVoiceMs = 0;
    private ?int $earlyAnnouncementStartedAtMs = null;
    private int $earlyLastVoiceEndedAtMs = 0;
    private int $earlyAnnouncementSpanMs = 0;

    private int $postAnswerElapsedMs = 0;
    private bool $postAnswerVoiceActive = false;
    private int $postAnswerVoiceStartedAtMs = 0;
    private ?int $postAnswerFirstVoiceStartedAtMs = null;
    private int $postAnswerLastVoiceEndedAtMs = 0;
    private int $postAnswerCurrentVoiceMs = 0;
    private int $postAnswerCurrentGapMs = 0;
    private int $postAnswerLastSpeechMs = 0;
    private int $postAnswerSilenceAfterSpeechMs = 0;
    private int $postAnswerSpeechSegments = 0;
    private int $postAnswerTotalVoiceMs = 0;
    private int $postAnswerLongestVoiceMs = 0;
    private bool $voiceCrossedAnswer = false;
    private int $earlyVoiceAtAnswerMs = 0;
    private bool $announcementContinuedAfterAnswer = false;

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
        private readonly int $earlyAnnouncementMinimumSegments = 3,
        private readonly int $earlyAnnouncementMinimumVoiceMs = 800,
        private readonly int $earlyAnnouncementMinimumSpanMs = 1500,
        private readonly int $postAnswerInternalGapMs = 400,
        private readonly int $postAnswerMachineMinimumSegments = 3,
        private readonly int $postAnswerMachineMinimumVoiceMs = 800,
        private readonly int $postAnswerMachineMinimumSpanMs = 1500,
        private readonly int $earlyToAnswerMaximumGapMs = 1200,
        private readonly int $answerToPostVoiceMaximumGapMs = 500,
    ) {
        if ($sampleRate <= 0 || $frameDurationMs <= 0) {
            throw new InvalidArgumentException('sampleRate e frameDurationMs devem ser maiores que zero.');
        }
        if ($analysisWindowMs < $frameDurationMs) {
            throw new InvalidArgumentException('analysisWindowMs deve ser maior ou igual a frameDurationMs.');
        }
        if ($humanMinimumSpeechMs < 0 || $humanMaximumSpeechMs < $humanMinimumSpeechMs) {
            throw new InvalidArgumentException('Faixa de fala humana inválida.');
        }
        if ($machineGreetingVoiceMs <= 0 || $postAnswerAnalysisTimeoutMs <= 0) {
            throw new InvalidArgumentException('Limites de análise devem ser maiores que zero.');
        }
        if ($earlyAnnouncementMinimumSegments <= 0 || $earlyAnnouncementMinimumVoiceMs <= 0 || $earlyAnnouncementMinimumSpanMs <= 0) {
            throw new InvalidArgumentException('Limites de anúncio early media inválidos.');
        }
        if ($postAnswerInternalGapMs < $frameDurationMs || $postAnswerMachineMinimumSegments <= 0 || $postAnswerMachineMinimumVoiceMs <= 0 || $postAnswerMachineMinimumSpanMs <= 0) {
            throw new InvalidArgumentException('Limites de análise pós-atendimento inválidos.');
        }
        if ($earlyToAnswerMaximumGapMs < 0 || $answerToPostVoiceMaximumGapMs < 0) {
            throw new InvalidArgumentException('Intervalos de continuidade não podem ser negativos.');
        }

        $this->frameBytes = (int) round($sampleRate * ($frameDurationMs / 1000)) * 2;
        $this->analysisWindowBytes = (int) round($sampleRate * ($analysisWindowMs / 1000)) * 2;
    }

    public function onVoiceStart(callable $callback): self { $this->onVoiceStart = Closure::fromCallable($callback); return $this; }
    public function onVoiceEnd(callable $callback): self { $this->onVoiceEnd = Closure::fromCallable($callback); return $this; }
    public function onGreetingDetected(callable $callback): self { $this->onGreetingDetected = Closure::fromCallable($callback); return $this; }
    public function onAnalysis(callable $callback): self { $this->onAnalysis = Closure::fromCallable($callback); return $this; }
    public function onAnswerBoundary(callable $callback): self { $this->onAnswerBoundary = Closure::fromCallable($callback); return $this; }
    public function onHumanLikely(callable $callback): self { $this->onHumanLikely = Closure::fromCallable($callback); return $this; }
    public function onMachineLikely(callable $callback): self { $this->onMachineLikely = Closure::fromCallable($callback); return $this; }
    public function onUnknown(callable $callback): self { $this->onUnknown = Closure::fromCallable($callback); return $this; }
    public function onAmdResult(callable $callback): self { $this->onAmdResult = Closure::fromCallable($callback); return $this; }

    public function push(string $pcmData): void
    {
        if ($pcmData === '') return;
        $this->frameBuffer .= $pcmData;
        while (strlen($this->frameBuffer) >= $this->frameBytes) {
            $frame = substr($this->frameBuffer, 0, $this->frameBytes);
            $this->frameBuffer = substr($this->frameBuffer, $this->frameBytes);
            $this->processFrame($frame);
        }
    }

    public function markAnswered(): void
    {
        if ($this->answered) return;

        $crossedVoiceMs = $this->voiceActive ? $this->voiceFramesMs : 0;
        if ($this->voiceActive) $this->finishEarlyVoiceSegment('answer_boundary');

        $this->answered = true;
        $this->answeredAtMs = $this->elapsedMs;
        $this->voiceCrossedAnswer = $crossedVoiceMs > 0;
        $this->earlyVoiceAtAnswerMs = $crossedVoiceMs;

        if ($this->voiceCrossedAnswer) {
            $this->postAnswerVoiceActive = true;
            $this->postAnswerVoiceStartedAtMs = $this->elapsedMs;
            $this->postAnswerFirstVoiceStartedAtMs = $this->elapsedMs;
            $this->postAnswerSpeechSegments = 1;
            $this->announcementContinuedAfterAnswer = $this->earlyAnnouncementDetected;
        }

        $event = [
            'audio_ms' => $this->elapsedMs,
            'early_voice_active' => $this->voiceCrossedAnswer,
            'early_voice_started_ms' => $this->earlyAnnouncementStartedAtMs,
            'early_voice_ms' => $this->earlyTotalVoiceMs,
            'early_active_voice_ms' => $crossedVoiceMs,
            'early_speech_segments' => $this->earlySpeechSegments,
            'early_announcement_span_ms' => $this->earlyAnnouncementSpanMs,
            'early_last_voice_ended_at_ms' => $this->earlyLastVoiceEndedAtMs,
            'early_gap_to_answer_ms' => $this->earlyLastVoiceEndedAtMs > 0 ? max(0, $this->elapsedMs - $this->earlyLastVoiceEndedAtMs) : null,
            'early_announcement_detected' => $this->earlyAnnouncementDetected,
            'greeting_detected' => $this->earlyGreetingDetected,
            'voice_crossed_answer' => $this->voiceCrossedAnswer,
        ];
        if ($this->onAnswerBoundary) ($this->onAnswerBoundary)($event);
    }

    public function finish(string $reason = 'finished'): void
    {
        if (!$this->answered && $this->voiceActive) $this->finishEarlyVoiceSegment($reason);
        if ($this->answered && $this->postAnswerVoiceActive) $this->finishPostAnswerVoiceSegment($reason);
        if ($this->answered && $this->amdResult === null) $this->evaluatePostAnswerMachine();
        if ($this->answered && $this->amdResult === null) $this->emitAmdResult('unknown', $reason);
    }

    public function isGreetingDetected(): bool { return $this->earlyGreetingDetected; }
    public function isAnswered(): bool { return $this->answered; }
    public function getAnsweredAtMs(): ?int { return $this->answeredAtMs; }
    public function getAmdResult(): ?string { return $this->amdResult; }
    public function getAmdReason(): ?string { return $this->amdReason; }

    private function processFrame(string $frame): void
    {
        $frameStartMs = $this->elapsedMs;
        $this->elapsedMs += $this->frameDurationMs;
        $this->analysisBuffer .= $frame;
        if (strlen($this->analysisBuffer) > $this->analysisWindowBytes) {
            $this->analysisBuffer = substr($this->analysisBuffer, -$this->analysisWindowBytes);
        }

        $samples = $this->decodePcm16LittleEndian($frame);
        if ($samples === []) return;
        $rmsDbfs = $this->calculateRmsDbfs($samples);
        $tone425 = ['detected' => false, 'frequency_hz' => 0.0, 'level_dbfs' => -120.0, 'prominence_db' => 0.0];
        if (strlen($this->analysisBuffer) >= $this->analysisWindowBytes) {
            $tone425 = $this->analyzeTone425($this->decodePcm16LittleEndian($this->analysisBuffer));
        }

        $threshold = max($this->minimumVoiceDbfs, $this->noiseFloorDbfs + $this->noiseMarginDb);
        $isVoice = $rmsDbfs >= $threshold && !$tone425['detected'];
        if (!$isVoice && !$tone425['detected'] && $rmsDbfs < -40.0) {
            $this->noiseFloorDbfs = ($this->noiseFloorDbfs * 0.98) + ($rmsDbfs * 0.02);
        }

        $analysis = [
            'audio_ms' => $frameStartMs,
            'phase' => $this->answered ? 'answered' : 'early_media',
            'rms_dbfs' => $rmsDbfs,
            'noise_floor_dbfs' => $this->noiseFloorDbfs,
            'voice_threshold_dbfs' => $threshold,
            'voice' => $isVoice,
            'tone_425' => $tone425,
            'early_speech_segments' => $this->earlySpeechSegments,
            'early_total_voice_ms' => $this->earlyTotalVoiceMs,
            'early_announcement_detected' => $this->earlyAnnouncementDetected,
            'post_answer_speech_segments' => $this->postAnswerSpeechSegments,
            'post_answer_total_voice_ms' => $this->postAnswerTotalVoiceMs,
            'announcement_continued_after_answer' => $this->announcementContinuedAfterAnswer,
            'amd_result' => $this->amdResult,
        ];
        if ($this->onAnalysis) ($this->onAnalysis)($analysis);

        if (!$this->answered) {
            $this->updateEarlyVoiceState($isVoice, $frameStartMs, $analysis);
            return;
        }
        $this->updatePostAnswerState($isVoice, $frameStartMs, $analysis);
    }

    private function updateEarlyVoiceState(bool $isVoice, int $frameStartMs, array $analysis): void
    {
        if ($isVoice) {
            if (!$this->voiceActive) {
                $this->voiceActive = true;
                $this->voiceStartedAtMs = $frameStartMs;
                $this->voiceFramesMs = 0;
                $this->voiceGapMs = 0;
                $this->greetingReported = false;
                $this->earlyAnnouncementStartedAtMs ??= $frameStartMs;
                if ($this->onVoiceStart) ($this->onVoiceStart)(['audio_ms' => $frameStartMs, 'rms_dbfs' => $analysis['rms_dbfs'], 'phase' => 'early_media']);
            }
            $this->voiceFramesMs += $this->frameDurationMs;
            $this->voiceGapMs = 0;
            if (!$this->greetingReported && $this->voiceFramesMs >= $this->minimumGreetingVoiceMs) {
                $this->greetingReported = true;
                $this->reportEarlyAnnouncement('early_greeting', $frameStartMs + $this->frameDurationMs);
            }
            $this->evaluateEarlyAnnouncement($frameStartMs + $this->frameDurationMs);
            return;
        }

        if (!$this->voiceActive) return;
        $this->voiceGapMs += $this->frameDurationMs;
        if ($this->voiceGapMs > $this->maximumInternalGapMs) $this->finishEarlyVoiceSegment('silence');
    }

    private function finishEarlyVoiceSegment(string $reason): void
    {
        if (!$this->voiceActive) return;
        $endedAtMs = max($this->voiceStartedAtMs, $this->elapsedMs - $this->voiceGapMs);
        $this->earlySpeechSegments++;
        $this->earlyTotalVoiceMs += $this->voiceFramesMs;
        $this->earlyLastVoiceEndedAtMs = $endedAtMs;
        $this->earlyAnnouncementStartedAtMs ??= $this->voiceStartedAtMs;
        $this->earlyAnnouncementSpanMs = max(0, $endedAtMs - $this->earlyAnnouncementStartedAtMs);

        $event = [
            'audio_ms' => $this->elapsedMs,
            'started_at_ms' => $this->voiceStartedAtMs,
            'ended_at_ms' => $endedAtMs,
            'voiced_ms' => $this->voiceFramesMs,
            'gap_ms' => $this->voiceGapMs,
            'greeting_detected' => $this->greetingReported,
            'early_announcement_detected' => $this->earlyAnnouncementDetected,
            'early_speech_segments' => $this->earlySpeechSegments,
            'early_total_voice_ms' => $this->earlyTotalVoiceMs,
            'early_announcement_span_ms' => $this->earlyAnnouncementSpanMs,
            'speech_segments' => $this->earlySpeechSegments,
            'reason' => $reason,
            'phase' => 'early_media',
        ];
        if ($this->onVoiceEnd) ($this->onVoiceEnd)($event);

        $this->voiceActive = false;
        $this->voiceStartedAtMs = 0;
        $this->voiceFramesMs = 0;
        $this->voiceGapMs = 0;
        $this->greetingReported = false;
        $this->evaluateEarlyAnnouncement($endedAtMs);
    }

    private function evaluateEarlyAnnouncement(int $endMs): void
    {
        if ($this->earlyAnnouncementDetected || $this->earlyAnnouncementStartedAtMs === null) return;
        $segments = $this->earlySpeechSegments + ($this->voiceActive ? 1 : 0);
        $voiceMs = $this->earlyTotalVoiceMs + ($this->voiceActive ? $this->voiceFramesMs : 0);
        $spanMs = max(0, $endMs - $this->earlyAnnouncementStartedAtMs);
        if ($segments >= $this->earlyAnnouncementMinimumSegments && $voiceMs >= $this->earlyAnnouncementMinimumVoiceMs && $spanMs >= $this->earlyAnnouncementMinimumSpanMs) {
            $this->reportEarlyAnnouncement('early_announcement', $endMs);
        }
    }

    private function reportEarlyAnnouncement(string $classification, int $endMs): void
    {
        if ($this->earlyAnnouncementDetected) return;
        $segments = $this->earlySpeechSegments + ($this->voiceActive ? 1 : 0);
        $voiceMs = $this->earlyTotalVoiceMs + ($this->voiceActive ? $this->voiceFramesMs : 0);
        $startedAtMs = $this->earlyAnnouncementStartedAtMs ?? $this->voiceStartedAtMs;
        $spanMs = max(0, $endMs - $startedAtMs);
        $this->earlyGreetingDetected = true;
        $this->earlyAnnouncementDetected = true;
        $this->earlyAnnouncementSpanMs = max($this->earlyAnnouncementSpanMs, $spanMs);
        if ($this->onGreetingDetected) {
            ($this->onGreetingDetected)([
                'audio_ms' => $endMs,
                'started_at_ms' => $startedAtMs,
                'voiced_ms' => $voiceMs,
                'total_voice_ms' => $voiceMs,
                'speech_segments' => $segments,
                'span_ms' => $spanMs,
                'phase' => 'early_media',
                'classification' => $classification,
            ]);
        }
    }

    private function updatePostAnswerState(bool $isVoice, int $frameStartMs, array $analysis): void
    {
        if ($this->answeredAtMs === null) return;
        $this->postAnswerElapsedMs = max(0, $this->elapsedMs - $this->answeredAtMs);
        if ($this->amdResult !== null) return;

        if ($isVoice) {
            if (!$this->postAnswerVoiceActive) {
                $this->postAnswerVoiceActive = true;
                $this->postAnswerVoiceStartedAtMs = $frameStartMs;
                $this->postAnswerFirstVoiceStartedAtMs ??= $frameStartMs;
                $this->postAnswerCurrentVoiceMs = 0;
                $this->postAnswerCurrentGapMs = 0;
                $this->postAnswerSilenceAfterSpeechMs = 0;
                $this->postAnswerSpeechSegments++;
                $this->evaluateAnnouncementContinuation($frameStartMs);
                if ($this->onVoiceStart) ($this->onVoiceStart)(['audio_ms' => $frameStartMs, 'post_answer_ms' => $this->postAnswerElapsedMs, 'rms_dbfs' => $analysis['rms_dbfs'], 'phase' => 'answered']);
            }
            $this->postAnswerCurrentVoiceMs += $this->frameDurationMs;
            $this->postAnswerTotalVoiceMs += $this->frameDurationMs;
            $this->postAnswerCurrentGapMs = 0;
            $this->postAnswerSilenceAfterSpeechMs = 0;
            $this->postAnswerLongestVoiceMs = max($this->postAnswerLongestVoiceMs, $this->postAnswerCurrentVoiceMs);
            $continuousMs = $this->postAnswerCurrentVoiceMs + ($this->voiceCrossedAnswer ? $this->earlyVoiceAtAnswerMs : 0);
            if ($continuousMs >= $this->machineGreetingVoiceMs) {
                $this->emitAmdResult('machine_likely', 'long_greeting');
                return;
            }
            if ($this->evaluatePostAnswerMachine()) return;
        } elseif ($this->postAnswerVoiceActive) {
            $this->postAnswerCurrentGapMs += $this->frameDurationMs;
            if ($this->postAnswerCurrentGapMs > $this->postAnswerInternalGapMs) $this->finishPostAnswerVoiceSegment('silence');
        } elseif ($this->postAnswerSpeechSegments > 0) {
            $this->postAnswerSilenceAfterSpeechMs += $this->frameDurationMs;
            if (
                !$this->earlyAnnouncementDetected &&
                !$this->voiceCrossedAnswer &&
                !$this->announcementContinuedAfterAnswer &&
                $this->postAnswerSpeechSegments === 1 &&
                $this->postAnswerLastSpeechMs >= $this->humanMinimumSpeechMs &&
                $this->postAnswerLastSpeechMs <= $this->humanMaximumSpeechMs &&
                $this->postAnswerSilenceAfterSpeechMs >= $this->humanSilenceAfterSpeechMs
            ) {
                $this->emitAmdResult('human_likely', 'short_greeting_followed_by_silence');
                return;
            }
        }

        if ($this->amdResult === null && $this->postAnswerElapsedMs >= $this->postAnswerAnalysisTimeoutMs) {
            $this->emitAmdResult('unknown', 'analysis_timeout');
        }
    }

    private function finishPostAnswerVoiceSegment(string $reason): void
    {
        if (!$this->postAnswerVoiceActive) return;
        $endedAtMs = max($this->postAnswerVoiceStartedAtMs, $this->elapsedMs - $this->postAnswerCurrentGapMs);
        $this->postAnswerLastSpeechMs = $this->postAnswerCurrentVoiceMs;
        $this->postAnswerLastVoiceEndedAtMs = $endedAtMs;
        $this->postAnswerLongestVoiceMs = max($this->postAnswerLongestVoiceMs, $this->postAnswerLastSpeechMs);

        $event = [
            'audio_ms' => $this->elapsedMs,
            'post_answer_ms' => $this->postAnswerElapsedMs,
            'started_at_ms' => $this->postAnswerVoiceStartedAtMs,
            'ended_at_ms' => $endedAtMs,
            'voiced_ms' => $this->postAnswerLastSpeechMs,
            'gap_ms' => $this->postAnswerCurrentGapMs,
            'speech_segments' => $this->postAnswerSpeechSegments,
            'total_voice_ms' => $this->postAnswerTotalVoiceMs,
            'greeting_span_ms' => $this->getPostAnswerGreetingSpanMs(),
            'greeting_detected' => false,
            'voice_crossed_answer' => $this->voiceCrossedAnswer,
            'announcement_continued_after_answer' => $this->announcementContinuedAfterAnswer,
            'reason' => $reason,
            'phase' => 'answered',
        ];
        if ($this->onVoiceEnd) ($this->onVoiceEnd)($event);

        $this->postAnswerVoiceActive = false;
        $this->postAnswerVoiceStartedAtMs = 0;
        $this->postAnswerCurrentVoiceMs = 0;
        $this->postAnswerCurrentGapMs = 0;
        $this->postAnswerSilenceAfterSpeechMs = 0;
        $this->evaluatePostAnswerMachine();
    }

    private function evaluateAnnouncementContinuation(int $voiceStartedAtMs): void
    {
        if ($this->announcementContinuedAfterAnswer || !$this->earlyAnnouncementDetected || $this->answeredAtMs === null || $this->earlyLastVoiceEndedAtMs <= 0) return;
        $earlyGap = max(0, $this->answeredAtMs - $this->earlyLastVoiceEndedAtMs);
        $postGap = max(0, $voiceStartedAtMs - $this->answeredAtMs);
        if ($earlyGap <= $this->earlyToAnswerMaximumGapMs && $postGap <= $this->answerToPostVoiceMaximumGapMs) {
            $this->announcementContinuedAfterAnswer = true;
        }
    }

    private function evaluatePostAnswerMachine(): bool
    {
        if ($this->amdResult !== null) return true;
        $spanMs = $this->getPostAnswerGreetingSpanMs();
        if (
            $this->postAnswerSpeechSegments < $this->postAnswerMachineMinimumSegments ||
            $this->postAnswerTotalVoiceMs < $this->postAnswerMachineMinimumVoiceMs ||
            $spanMs < $this->postAnswerMachineMinimumSpanMs
        ) return false;

        $reason = $this->announcementContinuedAfterAnswer ? 'early_announcement_continued_after_answer' : 'multi_segment_greeting';
        $this->emitAmdResult('machine_likely', $reason);
        return true;
    }

    private function getPostAnswerGreetingSpanMs(): int
    {
        if ($this->postAnswerFirstVoiceStartedAtMs === null) return 0;
        $endMs = $this->postAnswerVoiceActive ? $this->elapsedMs : $this->postAnswerLastVoiceEndedAtMs;
        return $endMs > 0 ? max(0, $endMs - $this->postAnswerFirstVoiceStartedAtMs) : 0;
    }

    private function emitAmdResult(string $classification, string $reason): void
    {
        if ($this->amdResult !== null) return;
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
            'early_announcement_detected' => $this->earlyAnnouncementDetected,
            'early_voice_at_answer_ms' => $this->earlyVoiceAtAnswerMs,
            'early_speech_segments' => $this->earlySpeechSegments,
            'early_total_voice_ms' => $this->earlyTotalVoiceMs,
            'early_announcement_span_ms' => $this->earlyAnnouncementSpanMs,
            'early_last_voice_ended_at_ms' => $this->earlyLastVoiceEndedAtMs,
            'announcement_continued_after_answer' => $this->announcementContinuedAfterAnswer,
            'speech_segments' => $this->postAnswerSpeechSegments,
            'current_speech_ms' => $this->postAnswerCurrentVoiceMs,
            'last_speech_ms' => $this->postAnswerLastSpeechMs,
            'total_voice_ms' => $this->postAnswerTotalVoiceMs,
            'longest_voice_ms' => $this->postAnswerLongestVoiceMs,
            'greeting_span_ms' => $this->getPostAnswerGreetingSpanMs(),
            'silence_after_speech_ms' => $this->postAnswerSilenceAfterSpeechMs,
        ];
        if ($this->onAmdResult) ($this->onAmdResult)($event);
        if ($classification === 'human_likely' && $this->onHumanLikely) { ($this->onHumanLikely)($event); return; }
        if ($classification === 'machine_likely' && $this->onMachineLikely) { ($this->onMachineLikely)($event); return; }
        if ($classification === 'unknown' && $this->onUnknown) ($this->onUnknown)($event);
    }

    private function analyzeTone425(array $samples): array
    {
        if ($samples === []) return ['detected' => false, 'frequency_hz' => 0.0, 'level_dbfs' => -120.0, 'prominence_db' => 0.0];
        $windowed = $this->applyHannWindow($samples);
        $bestFrequency = 425.0;
        $bestLevel = -120.0;
        for ($frequency = 395; $frequency <= 455; $frequency += 5) {
            $level = $this->goertzelDbfs($windowed, (float) $frequency);
            if ($level > $bestLevel) { $bestLevel = $level; $bestFrequency = (float) $frequency; }
        }
        for ($frequency = max(395, (int) $bestFrequency - 5); $frequency <= min(455, (int) $bestFrequency + 5); $frequency++) {
            $level = $this->goertzelDbfs($windowed, (float) $frequency);
            if ($level > $bestLevel) { $bestLevel = $level; $bestFrequency = (float) $frequency; }
        }
        $levels = [];
        foreach ([250.0, 300.0, 350.0, 500.0, 600.0, 700.0, 850.0, 1000.0, 1200.0] as $frequency) {
            $levels[] = $this->goertzelDbfs($windowed, $frequency);
        }
        $prominence = $bestLevel - $this->median($levels);
        return [
            'detected' => $bestLevel >= $this->tone425MinimumDbfs && $prominence >= $this->tone425MinimumProminenceDb,
            'frequency_hz' => $bestFrequency,
            'level_dbfs' => $bestLevel,
            'prominence_db' => $prominence,
        ];
    }

    private function decodePcm16LittleEndian(string $pcm): array
    {
        if ($pcm === '' || strlen($pcm) % 2 !== 0) return [];
        $values = unpack('v*', $pcm);
        if ($values === false) return [];
        $samples = [];
        foreach ($values as $value) $samples[] = (float) ($value >= 0x8000 ? $value - 0x10000 : $value);
        return $samples;
    }

    private function calculateRmsDbfs(array $samples): float
    {
        if ($samples === []) return -120.0;
        $sum = 0.0;
        foreach ($samples as $sample) $sum += $sample * $sample;
        $rms = sqrt($sum / count($samples));
        return $rms > 0.0 ? max(-120.0, 20.0 * log10($rms / 32768.0)) : -120.0;
    }

    private function applyHannWindow(array $samples): array
    {
        $count = count($samples);
        if ($count <= 1) return $samples;
        $result = [];
        foreach ($samples as $index => $sample) {
            $result[] = $sample * (0.5 * (1.0 - cos((2.0 * M_PI * $index) / ($count - 1))));
        }
        return $result;
    }

    private function goertzelDbfs(array $samples, float $frequency): float
    {
        $count = count($samples);
        if ($count === 0) return -120.0;
        $coefficient = 2.0 * cos((2.0 * M_PI * $frequency) / $this->sampleRate);
        $previous = 0.0;
        $previousPrevious = 0.0;
        foreach ($samples as $sample) {
            $current = $sample + ($coefficient * $previous) - $previousPrevious;
            $previousPrevious = $previous;
            $previous = $current;
        }
        $power = ($previous * $previous) + ($previousPrevious * $previousPrevious) - ($coefficient * $previous * $previousPrevious);
        if ($power <= 0.0) return -120.0;
        $amplitude = (4.0 * sqrt($power)) / $count;
        return $amplitude > 0.0 ? max(-120.0, min(0.0, 20.0 * log10($amplitude / 32768.0))) : -120.0;
    }

    private function median(array $values): float
    {
        if ($values === []) return -120.0;
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        return $count % 2 === 1 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2.0;
    }
}
