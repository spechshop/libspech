<?php
declare(strict_types=1);
namespace libspech\Rtp;

/** Observer only: never changes relay ordering or inserts concealment audio. */
final class AcousticRtpContinuity
{
    private ?array $last = null;
    // Bounded receipt window: late arrivals repair estimated loss, never feed old PCM.
    private int $received = 0;
    private int $span = 0;
    public int $duplicates = 0;
    public int $late = 0;
    public int $lost = 0;

    /** accept=false drops from analysis only; reset invalidates all prior evidence. */
    public function inspect(rtpc $packet, string $format, int $nowNs, int $eventRate = 0): array
    {
        $reset = null;
        $last = $this->last;
        if ($last !== null && $packet->ssrc === $last['ssrc']) {
            $delta = ($packet->sequence - $last['seq']) & 0xffff;
            if ($delta === 0) { $this->duplicates++; return ['accept' => false, 'reset' => 'duplicate']; }
            if ($delta >= 0x8000) {
                $age = ($last['seq'] - $packet->sequence) & 0xffff;
                if ($age < $this->span && ($this->received & (1 << $age)) !== 0) {
                    $this->duplicates++;
                    return ['accept' => false, 'reset' => 'duplicate'];
                }
                if ($age < $this->span) {
                    $this->received |= 1 << $age;
                    $this->lost--;
                }
                $this->late++;
                return ['accept' => false, 'reset' => 'late_or_reordered'];
            }
            $this->received = $delta >= 63 ? 1 : (($this->received << $delta) & PHP_INT_MAX) | 1;
            $this->span = min(63, $this->span + $delta);
            if ($delta > 1) { $this->lost += $delta - 1; $reset = 'packet_loss'; }
            if ($nowNs - $last['ns'] > 200_000_000) $reset ??= 'arrival_gap';
            if ($eventRate === 0 && $last['format'] !== null) {
                if ($format !== $last['format']) $reset = 'format_changed';
                elseif ($packet->timestamp !== $last['next_ts']) {
                    $advance = ($packet->timestamp - $last['next_ts']) & 0xffffffff;
                    $covered = $last['event_end'] === null ? 0 : (($last['event_end'] - $last['next_ts']) & 0xffffffff);
                    if ($advance >= 0x80000000 || $advance > $covered) $reset ??= 'timestamp_jump';
                }
            }
        } else {
            if ($last !== null) $reset = 'ssrc_changed';
            $last = ['format' => null, 'next_ts' => null, 'event_end' => null];
            $this->received = 1;
            $this->span = 1;
        }
        $eventEnd = $last['event_end'];
        if ($eventRate !== 0) {
            // Only a contiguous, same-clock event may explain skipped audio time.
            // END is not required: a received progress packet already proves coverage.
            if ($last['format'] !== null && (int)explode('/', $last['format'])[1] === $eventRate) {
                $duration = unpack('n', $packet->payloadRaw, 2)[1];
                $end = ($packet->timestamp + $duration) & 0xffffffff;
                $behind = ($last['next_ts'] - $packet->timestamp) & 0xffffffff;
                $previous = $last['event_end'] === null ? 0 : (($last['event_end'] - $last['next_ts']) & 0xffffffff);
                $ahead = ($packet->timestamp - $last['next_ts']) & 0xffffffff;
                if (($behind < 0x80000000 && $behind <= $duration) || $ahead <= $previous) {
                    $coverage = ($end - $last['next_ts']) & 0xffffffff;
                    if ($coverage < 0x80000000 && $coverage >= $previous) $eventEnd = $end;
                }
            }
        } else {
            // Keep coverage only while audio is still inside the current event.
            if ($format !== $last['format'] || $last['event_end'] === null
                || (($last['event_end'] - $packet->timestamp) & 0xffffffff) >= 0x80000000) $eventEnd = null;
        }
        $this->last = ['ssrc' => $packet->ssrc, 'seq' => $packet->sequence, 'ns' => $nowNs,
            'format' => $eventRate !== 0 ? $last['format'] : $format,
            'next_ts' => $eventRate !== 0 ? $last['next_ts'] : $packet->timestamp, 'event_end' => $eventEnd];
        return ['accept' => true, 'reset' => $reset];
    }

    public function duration(rtpc $packet, int $samples): void
    {
        $this->last['next_ts'] = ($packet->timestamp + $samples) & 0xffffffff;
        if ($this->last['event_end'] !== null
            && (($this->last['event_end'] - $this->last['next_ts']) & 0xffffffff) >= 0x80000000) $this->last['event_end'] = null;
    }
}
