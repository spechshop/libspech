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

    private float $noiseFloorDbfs = -65.0;

    private ?Closure $onVoiceStart = null;
    private ?Closure $onVoiceEnd = null;
    private ?Closure $onGreetingDetected = null;
    private ?Closure $onAnalysis = null;
    private ?Closure $onAnswerBoundary = null;

    public function __construct(
        private readonly int   $sampleRate = 8000,
        private readonly int   $frameDurationMs = 20,
        private readonly int   $analysisWindowMs = 200,
        private readonly int   $minimumGreetingVoiceMs = 800,
        private readonly int   $maximumInternalGapMs = 120,
        private readonly float $minimumVoiceDbfs = -42.0,
        private readonly float $noiseMarginDb = 10.0,
        private readonly float $tone425MinimumDbfs = -45.0,
        private readonly float $tone425MinimumProminenceDb = 14.0,
    )
    {
        if ($this->sampleRate <= 0) {
            throw new InvalidArgumentException(
                'sampleRate deve ser maior que zero.'
            );
        }

        if ($this->frameDurationMs <= 0) {
            throw new InvalidArgumentException(
                'frameDurationMs deve ser maior que zero.'
            );
        }

        $frameSamples = (int)round(
            $this->sampleRate * ($this->frameDurationMs / 1000)
        );

        $analysisSamples = (int)round(
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

    public function push(string $pcmData): void
    {
        if ($pcmData === '') {
            return;
        }

        $this->frameBuffer .= $pcmData;

        while (strlen($this->frameBuffer) >= $this->frameBytes) {
            $frame = substr(
                $this->frameBuffer,
                0,
                $this->frameBytes
            );

            $this->frameBuffer = substr(
                $this->frameBuffer,
                $this->frameBytes
            );

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

        $event = [
            'audio_ms' => $this->elapsedMs,
            'early_voice_active' => $this->voiceActive,
            'early_voice_started_ms' => $this->voiceActive
                ? $this->voiceStartedAtMs
                : null,
            'early_voice_ms' => $this->voiceFramesMs,
            'greeting_detected' => $this->greetingReported,
        ];

        if ($this->onAnswerBoundary !== null) {
            ($this->onAnswerBoundary)($event);
        }

        /*
         * A primeira experiência termina aqui.
         *
         * Não apagamos os dados, porque eles ainda podem ser
         * consultados depois do 200 OK.
         */
    }

    public function finish(string $reason = 'finished'): void
    {
        if ($this->voiceActive) {
            $this->finishVoiceSegment($reason);
        }
    }

    public function isGreetingDetected(): bool
    {
        return $this->greetingReported;
    }

    public function getAnsweredAtMs(): ?int
    {
        return $this->answeredAtMs;
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

        /*
         * Só analisamos o tom quando tivermos 200 ms acumulados.
         */
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

        /*
         * Ringback de 425 Hz possui energia alta e seria confundido
         * com voz por um VAD baseado apenas em volume.
         */
        $isVoice =
            $rmsDbfs >= $voiceThresholdDbfs &&
            !$tone425['detected'];

        /*
         * Atualiza o piso de ruído somente quando o frame não parece
         * ser voz e não é o tom de 425 Hz.
         */
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
        ];

        if ($this->onAnalysis !== null) {
            ($this->onAnalysis)($analysis);
        }

        /*
         * Para esta experiência, somente frames anteriores ao
         * 200 OK participam da detecção de saudação antecipada.
         */
        if ($this->answered) {
            return;
        }

        $this->updateVoiceState(
            isVoice: $isVoice,
            frameStartMs: $frameStartMs,
            analysis: $analysis
        );
    }

    private function updateVoiceState(
        bool  $isVoice,
        int   $frameStartMs,
        array $analysis
    ): void
    {
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

        /*
         * Pequenas pausas dentro da fala não encerram a saudação.
         */
        if ($this->voiceGapMs <= $this->maximumInternalGapMs) {
            return;
        }

        $this->finishVoiceSegment('silence');
    }

    private function finishVoiceSegment(string $reason): void
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
            'phase' => $this->answered
                ? 'answered'
                : 'early_media',
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

        /*
         * Primeiro faz uma busca grossa.
         */
        $bestFrequency = 425.0;
        $bestLevel = -120.0;

        for ($frequency = 395; $frequency <= 455; $frequency += 5) {
            $level = $this->goertzelDbfs(
                $windowed,
                (float)$frequency
            );

            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestFrequency = (float)$frequency;
            }
        }

        /*
         * Depois refina em passos de 1 Hz.
         */
        $refineStart = max(395, (int)$bestFrequency - 5);
        $refineEnd = min(455, (int)$bestFrequency + 5);

        for ($frequency = $refineStart; $frequency <= $refineEnd; $frequency++) {
            $level = $this->goertzelDbfs(
                $windowed,
                (float)$frequency
            );

            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestFrequency = (float)$frequency;
            }
        }

        /*
         * Frequências de referência fora da faixa do ringback.
         */
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

            $samples[] = (float)$value;
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

    private function goertzelDbfs(
        array $samples,
        float $frequency
    ): float
    {
        $sampleCount = count($samples);

        if ($sampleCount === 0) {
            return -120.0;
        }

        $omega = (
                2.0 * M_PI * $frequency
            ) / $this->sampleRate;

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

        /*
         * Correção aproximada para janela Hann.
         */
        $amplitude = (
                4.0 * $magnitude
            ) / $sampleCount;

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
            return (float)$values[$middle];
        }

        return (
                $values[$middle - 1] +
                $values[$middle]
            ) / 2.0;
    }
}