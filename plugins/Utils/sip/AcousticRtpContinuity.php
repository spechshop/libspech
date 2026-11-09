<?php
declare(strict_types=1);
namespace libspech\Rtp;

/** Observer only: never changes relay ordering or inserts concealment audio. */
final class AcousticRtpContinuity
{
    private ?array $last = null;
    public int $duplicates = 0;
    public int $late = 0;
    public int $lost = 0;

    /** accept=false drops from analysis only; reset invalidates all prior evidence. */
    public function inspect(rtpc $packet, string $format, int $nowNs): array
    {
        $reset = null;
        if ($this->last !== null) {
            $last = $this->last;
            if ($packet->ssrc !== $last['ssrc'] || $format !== $last['format']) {
                $reset = $packet->ssrc !== $last['ssrc'] ? 'ssrc_changed' : 'format_changed';
            } else {
                $delta = ($packet->sequence - $last['seq']) & 0xffff;
                if ($delta === 0) { $this->duplicates++; return ['accept' => false, 'reset' => 'duplicate']; }
                if ($delta >= 0x8000) { $this->late++; return ['accept' => false, 'reset' => 'late_or_reordered']; }
                if ($delta > 1) { $this->lost += $delta - 1; $reset = 'packet_loss'; }
                if ($packet->timestamp !== $last['next_ts']) $reset ??= 'timestamp_jump';
                if ($nowNs - $last['ns'] > 200_000_000) $reset ??= 'arrival_gap';
            }
        }
        $this->last = ['ssrc' => $packet->ssrc, 'seq' => $packet->sequence, 'format' => $format,
            'ns' => $nowNs, 'next_ts' => $packet->timestamp];
        return ['accept' => true, 'reset' => $reset];
    }

    public function duration(rtpc $packet, int $samples): void
    {
        $this->last['next_ts'] = ($packet->timestamp + $samples) & 0xffffffff;
    }
}
