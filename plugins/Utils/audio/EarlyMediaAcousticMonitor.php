<?php
declare(strict_types=1);

namespace libspech\audio;

/** Incremental, bounded PCM16LE/mono/8 kHz adapter. No SIP, I/O or wall timers. */
final class EarlyMediaAcousticMonitor
{
    private ?\AcousticStreamAnalyzer $analyzer = null;
    private EarlyMediaVoicePolicy $policy;
    private string $pending = '';
    private array $events = [];
    private array $seen = [];
    private ?array $decision = null;
    private int $audioMs = 0;
    private int $epoch = 0;
    private int $cpuNs = 0;
    private bool $closed = false;
    private bool $ringSeen = false;
    private array $frameCounts = ['voice_like' => 0, 'music_like' => 0, 'other_audio' => 0,
        'narrowband_tone' => 0, 'silence' => 0, 'noise' => 0];
    private array $discontinuities = [];
    private ?string $stopReason = null;
    private ?string $failureReason = null;

    public function __construct(int $minimumVoiceMs = 1000)
    {
        $this->policy = new EarlyMediaVoicePolicy($minimumVoiceMs);
        try {
            $this->analyzer = new \AcousticStreamAnalyzer(8000, 20);
            if (!array_key_exists('frame_signal', $this->analyzer->state())) {
                throw new \RuntimeException('psampler state.frame_signal required (voice debounce is not vocal duration)');
            }
            $weak = \WeakReference::create($this);
            foreach (['ring.started', 'ring.ended', 'ring.cadence_detected', 'ring.interrupted', 'speech.started', 'speech.ended', 'signal.changed'] as $name) {
                $this->analyzer->on($name, static function (array $event) use ($weak): void {
                    $self = $weak->get();
                    if ($self === null) return;
                    if ($event['type'] === 'ring.started') $self->ringSeen = true;
                    // Bound diagnostics: first occurrence of each transition/signal per attempt.
                    $key = $event['type'] . ':' . $event['signal'];
                    if (isset($self->seen[$key])) return;
                    $self->seen[$key] = true;
                    $self->record($event['type'], ['native' => $event]);
                });
            }
            $this->record('monitor.started', ['minimum_voice_ms' => $minimumVoiceMs]);
        } catch (\Throwable $error) {
            $this->fail($error->getMessage());
        }
    }

    public function push(string $pcm): void
    {
        if ($this->closed || $pcm === '') return;
        $start = hrtime(true);
        try {
            // Keep at most 319 bytes across calls; fragmentation may be odd.
            $offset = 0;
            $length = strlen($pcm);
            while ($offset < $length && !$this->closed) {
                $take = min(320 - strlen($this->pending), $length - $offset);
                $this->pending .= substr($pcm, $offset, $take);
                $offset += $take;
                if (strlen($this->pending) !== 320) continue;
                $frame = $this->pending;
                $this->pending = '';
                $this->audioMs += 20;
                $this->analyzer->push($frame);
                $state = $this->analyzer->state();
                // Reuse the native result already read for the policy; no extra DSP.
                if (isset($this->frameCounts[$state['frame_signal']])) $this->frameCounts[$state['frame_signal']]++;
                if (!$this->policy->frame($state['frame_signal'], $state['signal'])) continue;
                $this->record('voice.minimum_reached', ['qualified_voice_ms' => $this->policy->voiceMs()]);
                $this->decision = [
                    'classification' => 'early_voice_activity',
                    'mailbox_proven' => false,
                    'minimum_voice_ms' => $this->policy->metrics()['minimum_voice_ms'],
                    'qualified_voice_ms' => $this->policy->voiceMs(),
                    'audio_ms' => $this->audioMs,
                    'continuity_epoch' => $this->epoch,
                    'ring_seen' => $this->ringSeen,
                    'confirmed_ns' => hrtime(true),
                ];
                $this->record('acoustic.confirmed', $this->decision);
                $this->close('decision');
            }
        } catch (\Throwable $error) {
            $this->fail($error->getMessage());
        } finally {
            $this->cpuNs += hrtime(true) - $start;
        }
    }

    public function discontinuity(string $reason): void
    {
        if ($this->closed) return;
        $this->pending = '';
        $this->policy->resetEvidence();
        $this->ringSeen = false;
        $this->epoch++;
        $this->discontinuities[$reason] = ($this->discontinuities[$reason] ?? 0) + 1;
        $this->analyzer?->reset();
        if (!isset($this->seen['discontinuity:' . $reason])) {
            $this->seen['discontinuity:' . $reason] = true;
            $this->record('continuity.reset', ['reason' => $reason]);
        }
    }

    public function record(string $type, array $fields = []): void
    {
        if (count($this->events) < 64) $this->events[] = [
            'event' => $type, 'monotonic_ns' => hrtime(true),
            'audio_ms' => $this->audioMs, 'continuity_epoch' => $this->epoch,
        ] + $fields;
    }

    public function fail(string $reason): void
    {
        if ($this->closed) return;
        $this->failureReason = $reason;
        $this->record('analysis.failed', ['reason' => $reason]);
        $this->close('fail_open');
    }

    public function close(string $reason = 'finished'): void
    {
        if ($this->closed) return;
        $this->closed = true;
        $this->stopReason = $reason;
        $this->pending = '';
        $this->analyzer = null;
        // Always reserve the final summary, even if the bounded event buffer is full.
        if (count($this->events) >= 64) array_pop($this->events);
        $this->record('analysis.stopped', ['reason' => $reason] + $this->diagnostics());
    }

    public function isClosed(): bool { return $this->closed; }
    public function decision(): ?array { return $this->decision; }
    public function takeEvents(): array { $events = $this->events; $this->events = []; return $events; }
    public function metrics(): array
    {
        return ['audio_ms' => $this->audioMs, 'qualified_voice_ms' => $this->policy->voiceMs(),
            'processing_ns' => $this->cpuNs, 'pending_bytes' => strlen($this->pending),
            'continuity_epoch' => $this->epoch, 'closed' => $this->closed] + $this->diagnostics();
    }

    private function diagnostics(): array
    {
        return $this->policy->metrics() + ['frame_counts' => $this->frameCounts,
            'rtp_discontinuities' => $this->epoch, 'discontinuity_reasons' => $this->discontinuities,
            'decision_emitted' => $this->decision !== null, 'stop_reason' => $this->stopReason,
            'failure_reason' => $this->failureReason];
    }
}
