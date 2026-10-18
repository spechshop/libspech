<?php

declare(strict_types=1);

// End-to-end local UDP benchmark. The receiver is a separate OS process so a
// blocked Swoole reactor does not also delay our receive clock. No SIP is used.
require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpChannel;
use libspech\Sip\trunkController;

function udpCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function udpStats(array $values): array
{
    sort($values);
    $n = count($values);
    return [
        'mean' => array_sum($values) / max(1, $n),
        'median' => $values[(int)floor(($n - 1) * .5)] ?? 0,
        'p95' => $values[max(0, (int)ceil($n * .95) - 1)] ?? 0,
        'p99' => $values[max(0, (int)ceil($n * .99) - 1)] ?? 0,
        'max' => $values[$n - 1] ?? 0,
        'min' => $values[0] ?? 0,
    ];
}

final class MeasuredUdpSocket extends SocketMutable
{
    public array $times = [];
    public array $packets = [];
    public mixed $afterSend = null;

    public function sendto(string $addr, int $port, string $data): int|false
    {
        $this->times[] = hrtime(true);
        $sent = parent::sendto($addr, $port, $data);
        udpCheck($sent === strlen($data), 'UDP send failed');
        $this->packets[] = $data;
        ($this->afterSend)(count($this->packets));
        return $sent;
    }
}

function udpScenario(string $wav, string $codec, int $ptime, int $count, int $delay, int $calls, bool $sharing): array
{
    $rx = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    socket_bind($rx, '127.0.0.1', 0);
    socket_getsockname($rx, $address, $port);
    socket_set_option($rx, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 15, 'usec' => 0]);
    $capture = tempnam(sys_get_temp_dir(), 'libspech-udp-rx-');
    $pid = pcntl_fork();
    if ($pid === 0) {
        $received = [];
        while (($bytes = socket_recvfrom($rx, $data, 65535, 0, $from, $fromPort)) !== false) {
            if ($data === 'END') {
                break;
            }
            $header = unpack('Cx/Cpt/nseq/Nts/Nssrc', $data);
            $received[$header['ssrc']][] = [hrtime(true), $header['seq'], $header['ts'], hash('sha256', $data)];
        }
        file_put_contents($capture, json_encode($received));
        socket_close($rx);
        exit(0);
    }
    udpCheck($pid > 0, 'fork failed');
    socket_close($rx);

    $sockets = [];
    $trunks = [];
    $callbackCosts = [];
    $wall = hrtime(true);
    $cpu = getrusage();
    $memory = memory_get_usage(true);
    Swoole\Coroutine\run(function () use ($wav, $codec, $ptime, $count, $delay, $calls, $sharing, $port, &$sockets, &$trunks, &$callbackCosts): void {
        for ($call = 0; $call < $calls; $call++) {
            $longConcurrent = $calls > 1 && filesize($wav) > 1_000_000;
            if ($longConcurrent && $call > 0) {
                Swoole\Coroutine::sleep(.3);
            }
            $trunk = (new ReflectionClass(trunkController::class))->newInstanceWithoutConstructor();
            $trunk->codecName = $codec;
            $trunk->frequencyCall = $codec === 'OPUS' ? 48000 : 8000;
            $trunk->audioRemoteIp = '127.0.0.1';
            $trunk->audioRemotePort = $port;
            $trunk->callActive = true;
            $trunk->audioMemorySharingEnabled = $sharing;
            $trunk->setPacketTime($ptime);
            $sock = new MeasuredUdpSocket(AF_INET, SOCK_DGRAM, SOL_UDP);
            $media = new MediaChannel($sock, 'udp-validation');
            $media->setPacketTime($ptime);
            $pt = match ($codec) { 'PCMA' => 8, 'PCMU' => 0, 'G729' => 18, 'GSM' => 3, 'OPUS' => 111 };
            $media->addMember(['address' => '127.0.0.1', 'port' => $port, 'codec' => $codec,
                'pt' => $pt, 'frequency' => $trunk->frequencyCall, 'channels' => 1,
                'config' => $longConcurrent ? ['validation_call' => $call] : []]);
            $media->members['127.0.0.1:' . $port]['rtpChannel']->setSsrc(100 + $call);
            $trunk->mediaChannel = $media;
            $trunk->defineAudioFile($wav);
            $property = new ReflectionProperty($trunk, 'audioFileHandle');
            $original = $property->getValue($trunk);
            $property->setValue($trunk, static function ($peer, $phone) use ($original, &$callbackCosts): void {
                $start = hrtime(true);
                $original($peer, $phone);
                $callbackCosts[] = (hrtime(true) - $start) / 1e6;
            });
            $sock->afterSend = static function (int $n) use ($count, $delay, $media): void {
                if ($n === 20 && $delay > 0) {
                    // Deliberately stalls this call; <= ptime is absorbed by the
                    // existing deadline, larger delays must not cause catch-up.
                    Swoole\Coroutine::sleep($delay / 1000);
                }
                if ($n >= $count) {
                    $media->active = false;
                }
            };
            $sockets[] = $sock;
            $trunks[] = $trunk;
            Swoole\Coroutine::create(static function () use ($trunk): void {
                (new ReflectionMethod($trunk, 'runAudioTransmissionLoop'))->invoke($trunk);
                $trunk->stopAudioFile();
                $trunk->mediaChannel->socket->destroy();
                $trunk->mediaChannel->eventSock->destroy();
            });
        }
    });
    $wallMs = (hrtime(true) - $wall) / 1e6;
    $used = getrusage();
    $cpuMs = (($used['ru_utime.tv_sec'] - $cpu['ru_utime.tv_sec']) + ($used['ru_stime.tv_sec'] - $cpu['ru_stime.tv_sec'])) * 1000
        + (($used['ru_utime.tv_usec'] - $cpu['ru_utime.tv_usec']) + ($used['ru_stime.tv_usec'] - $cpu['ru_stime.tv_usec'])) / 1000;
    $end = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    socket_sendto($end, 'END', 3, 0, '127.0.0.1', $port);
    socket_close($end);
    pcntl_waitpid($pid, $status);
    udpCheck(pcntl_wexitstatus($status) === 0, 'Receiver failed');
    $received = json_decode(file_get_contents($capture), true, flags: JSON_THROW_ON_ERROR);
    unlink($capture);
    $txDeltas = $rxDeltas = [];
    $missedSlots = $lateIntervals = $packets = 0;
    foreach ($sockets as $call => $sock) {
        udpCheck(count($sock->packets) === $count, 'Wrong sender packet count');
        $observed = $received[100 + $call] ?? [];
        udpCheck(count($observed) === $count, 'UDP loss');
        $previous = null;
        foreach ($sock->packets as $i => $packet) {
            $h = unpack('Cx/Cpt/nseq/Nts/Nssrc', $packet);
            udpCheck(hash('sha256', $packet) === $observed[$i][3], 'UDP payload/header changed');
            if ($previous !== null) {
                udpCheck((($h['seq'] - $previous['seq']) & 0xffff) === 1, 'Sequence gap');
                udpCheck((($h['ts'] - $previous['ts']) & 0xffffffff) === (int)round($trunks[$call]->frequencyCall * $ptime / 1000), 'Timestamp gap');
                $delta = ($sock->times[$i] - $sock->times[$i - 1]) / 1e6;
                $txDeltas[] = $delta;
                $rxDeltas[] = ($observed[$i][0] - $observed[$i - 1][0]) / 1e6;
                $lateIntervals += (int)($delta > $ptime + 1);
                // Observable complete packet slots exceeded; this is not the
                // internal pacer's nanosecond deadline counter.
                $missedSlots += max(0, (int)floor(($delta - 1) / $ptime) - 1);
            }
            $previous = $h;
            $packets++;
        }
    }
    return ['codec' => $codec, 'ptime_ms' => $ptime, 'delay_ms' => $delay, 'calls' => $calls, 'sharing' => $sharing,
        'packets' => $packets, 'incorrect_or_lost' => 0, 'send_intervals_ms' => udpStats($txDeltas),
        'receiver_intervals_ms' => udpStats($rxDeltas), 'callback_ms' => udpStats($callbackCosts),
        'late_intervals_over_1ms' => $lateIntervals, 'missed_full_slots' => $missedSlots,
        'wall_ms' => $wallMs, 'cpu_ms' => $cpuMs, 'cpu_one_core_percent' => 100 * $cpuMs / $wallMs,
        'memory_start_bytes' => $memory, 'memory_peak_bytes' => memory_get_peak_usage(true),
        'max_rss_kib' => $used['ru_maxrss'], 'raw_send_times_ns' => array_map(fn($s) => $s->times, $sockets),
        'raw_receive_records' => $received];
}

$out = $argv[1] ?? null;
udpCheck($out !== null, 'Usage: php extra/validation/06_trunk_rtp_udp.php output.json');
$short = tempnam(sys_get_temp_dir(), 'libspech-short-');
$long = tempnam(sys_get_temp_dir(), 'libspech-long-');
$tone = '';
for ($i = 0; $i < 8000; $i++) {
    $tone .= pack('v', (int)(sin(2 * M_PI * 437 * $i / 8000) * 12000) & 0xffff);
}
extra_write_wav_pcm16le($short, substr($tone, 0, 8000), 8000, 1); // 0.5 s; loops repeatedly
extra_write_wav_pcm16le($long, str_repeat($tone, 120), 8000, 1);
$report = ['environment' => ['date_utc' => gmdate('c'), 'php' => PHP_VERSION, 'kernel' => php_uname(),
    'swoole' => phpversion('swoole'), 'receiver' => 'independent process, localhost UDP, userspace recvfrom clock'], 'results' => []];
try {
    $cases = [['PCMA', 10, 100, 0, 1, true, $short], ['PCMA', 20, 100, 0, 1, true, $short],
        ['PCMA', 40, 60, 0, 1, true, $short], ['PCMA', 20, 100, 0, 1, true, $short],
        ['PCMA', 20, 100, 0, 1, false, $long], ['PCMA', 20, 100, 0, 3, true, $short]];
    foreach ([5, 15, 30, 50, 100] as $delay) {
        $cases[] = ['PCMA', 20, 60, $delay, 1, true, $short];
    }
    foreach (['G729' => 'bcg729Channel', 'GSM' => 'gsmChannel', 'OPUS' => 'opusChannel'] as $codec => $class) {
        if (class_exists($class)) {
            $cases[] = [$codec, 20, 80, 0, 1, true, $short];
            $cases[] = [$codec, 20, 80, 0, 1, true, $short];
            $cases[] = [$codec, 20, 80, 0, 2, true, $long];
        } else {
            $report['skipped'][] = $codec . ': extension unavailable';
        }
    }
    foreach ($cases as [$codec, $ptime, $count, $delay, $calls, $sharing, $wav]) {
        $result = udpScenario($wav, $codec, $ptime, $count, $delay, $calls, $sharing);
        $result['file_seconds'] = $wav === $short ? .5 : 120;
        $report['results'][] = $result;
        printf("%s/%d delay=%d calls=%d sharing=%d file=%.1fs mean=%.3f p99=%.3f max=%.3f callback-max=%.3f ms\n",
            $codec, $ptime, $delay, $calls, $sharing, $result['file_seconds'], $result['send_intervals_ms']['mean'],
            $result['send_intervals_ms']['p99'], $result['send_intervals_ms']['max'], $result['callback_ms']['max']);
        file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    }
} finally {
    unlink($short);
    unlink($long);
}
