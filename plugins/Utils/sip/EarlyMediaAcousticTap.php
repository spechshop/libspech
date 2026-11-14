<?php
declare(strict_types=1);
namespace libspech\Rtp;

use libspech\audio\EarlyMediaAcousticMonitor;

/** Optional passive tap, called after the existing relay has decoded, if needed. */
final class EarlyMediaAcousticTap
{
    private AcousticRtpContinuity $continuity;
    private ?PcmStreamConverter $converter = null;
    private mixed $decoder = null;
    private ?string $decodePath = null;
    private bool $firstRtp = false;
    private ?\Closure $onEvent;
    private bool $released = false;
    private array $packetContext = [];
    private int $sharedPackets = 0;
    private int $decodedPackets = 0;

    public function __construct(public readonly EarlyMediaAcousticMonitor $monitor, ?callable $onEvent = null)
    {
        $this->continuity = new AcousticRtpContinuity();
        $this->onEvent = $onEvent === null ? null : \Closure::fromCallable($onEvent);
    }

    public function receive(rtpc $packet, array $member, string $codec, int $rate, int $channels, string|false|null $sharedPcm = null): void
    {
        if ($this->released || ($member['leg'] ?? '') !== 'b') return;
        try {
            if (!$this->monitor->isClosed()) $this->analyze($packet, $codec, $rate, $channels, $sharedPcm);
            $this->flush();
            if ($this->monitor->isClosed()) $this->release();
        } catch (\Throwable $error) {
            $this->monitor->fail($error->getMessage());
            $this->flush();
            $this->release();
        }
    }

    private function analyze(rtpc $packet, string $codec, int $rate, int $channels, string|false|null $sharedPcm): void
    {
        $event = $codec === 'TELEPHONE-EVENT';
        // Parser failures, unsupported comfort noise and malformed frames fail open.
        if ($packet->version !== 2 || $packet->padding !== 0 || $packet->payloadRaw === ''
            || ($event && strlen($packet->payloadRaw) < 4)
            || (!$event && (!in_array($codec, ['PCMA', 'PCMU', 'G729', 'OPUS'], true)
            || !in_array($channels, [1, 2], true) || !in_array($rate, [8000, 48000], true)
            || ($codec !== 'OPUS' && ($rate !== 8000 || $channels !== 1))
            || ($codec === 'G729' && strlen($packet->payloadRaw) % 10 !== 0)))) {
            $this->monitor->discontinuity('invalid_or_unsupported_packet');
            $this->resetConversion();
            return;
        }
        $stateful = $codec === 'G729' || $codec === 'OPUS'
            || ($event && in_array(explode('/', $this->packetContext['format'] ?? '')[0], ['G729', 'OPUS'], true));
        $path = $event ? ($this->decodePath ?? 'tap') : (is_string($sharedPcm) && $sharedPcm !== '' ? 'shared' : 'tap');
        $continuity = $this->continuity->inspect($packet, "$codec/$rate/$channels/{$packet->payloadType}", hrtime(true), $event ? $rate : 0);
        if (!$continuity['accept']) {
            // Relay may already have consumed a duplicate in its stateful decoder.
            // Its unobservable state cannot safely support an acoustic decision.
            if (!$event && $stateful && ($path === 'shared' || $this->decodePath === 'shared')) {
                $this->monitor->discontinuity($continuity['reset']);
                $this->monitor->fail('shared_decoder_' . $continuity['reset']);
            }
            return;
        }
        if ($stateful && $path === 'shared' && $continuity['reset'] !== null) {
            // We cannot reset a relay-owned decoder without changing the media path.
            // Fail open rather than qualify frames from an unverifiable codec history.
            $this->monitor->discontinuity($continuity['reset']);
            $this->monitor->fail('shared_decoder_' . $continuity['reset']);
            return;
        }
        if ($continuity['reset'] !== null || ($stateful && $this->decodePath !== null && $this->decodePath !== $path)) {
            $this->monitor->discontinuity($continuity['reset'] ?? 'decoder_path_changed');
            $this->resetConversion();
        }
        if ($event) return; // Transport observation only: never decode or push DTMF PCM.
        $this->decodePath = $path;
        $this->packetContext = ['ssrc' => $packet->ssrc, 'rtp_sequence' => $packet->sequence,
            'rtp_timestamp' => $packet->timestamp, 'format' => "$codec/$rate/$channels",
            'lost_packets' => $this->continuity->lost, 'duplicate_packets' => $this->continuity->duplicates,
            'late_packets' => $this->continuity->late];
        if ($path === 'shared') $this->sharedPackets++; else $this->decodedPackets++;
        $pcm = $path === 'shared' ? $sharedPcm : match ($codec) {
            'PCMA' => \decodePcmaToPcm($packet->payloadRaw),
            'PCMU' => \decodePcmuToPcm($packet->payloadRaw),
            'G729' => ($this->decoder ??= new \bcg729Channel())->decode($packet->payloadRaw),
            'OPUS' => ($this->decoder ??= new \opusChannel($rate, $channels))->decode($packet->payloadRaw),
        };
        if (!is_string($pcm) || $pcm === '' || strlen($pcm) % ($channels * 2) !== 0) {
            throw new \RuntimeException('invalid_decoded_pcm');
        }
        $samples = intdiv(strlen($pcm), $channels * 2);
        if ($samples * 1000 > $rate * 120) throw new \RuntimeException('oversized_audio_packet');
        if (!$this->firstRtp) { $this->firstRtp = true; $this->monitor->record('rtp.first_valid', $this->packetContext); }
        $this->continuity->duration($packet, $samples);
        $this->converter ??= new PcmStreamConverter($rate, $channels, 8000, 1);
        $this->monitor->push($this->converter->push($pcm));
    }

    private function resetConversion(): void
    {
        $this->converter = null;
        if ($this->decoder !== null && method_exists($this->decoder, 'close')) $this->decoder->close();
        $this->decoder = null;
    }

    private function flush(): void
    {
        foreach ($this->monitor->takeEvents() as $event) {
            if ($this->onEvent === null) break;
            try {
                ($this->onEvent)($event + ['lost_packets' => $this->continuity->lost,
                    'duplicate_packets' => $this->continuity->duplicates, 'late_packets' => $this->continuity->late]
                    + $this->packetContext);
            } catch (\Throwable $error) {
                // A failed consumer must never escape into the media receive loop.
                $this->onEvent = null;
                $this->monitor->fail('event_delivery_failed: ' . $error->getMessage());
                $this->monitor->takeEvents();
                $this->release();
                break;
            }
        }
    }

    private function release(): void
    {
        $this->released = true;
        $this->resetConversion();
        $this->onEvent = null;
    }

    public function metrics(): array
    {
        return $this->monitor->metrics() + ['shared_pcm_packets' => $this->sharedPackets,
            'analysis_decode_packets' => $this->decodedPackets, 'lost_packets' => $this->continuity->lost,
            'duplicate_packets' => $this->continuity->duplicates, 'late_packets' => $this->continuity->late];
    }

    public function close(string $reason = 'finished'): void
    {
        $this->monitor->close($reason);
        $this->flush();
        $this->release();
    }
}
