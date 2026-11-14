<?php

ini_set('memory_limit', '1024M');
$root = realpath($argv[1]);
chdir($root);
require $root.'/plugins/autoloader.php';
use libspech\Rtp\{MediaChannel,EarlyMediaAcousticTap};
use libspech\audio\EarlyMediaAcousticMonitor;
use Swoole\Coroutine as Co;

$scenario = $argv[2] ?? 'acoustic';
$n = (int)($argv[3] ?? 60);
function cpu(array $r): float
{
    return $r['ru_utime.tv_sec'] + $r['ru_utime.tv_usec'] / 1e6 + $r['ru_stime.tv_sec'] + $r['ru_stime.tv_usec'] / 1e6;
}
if ($scenario === 'micro') {
    $mon = new EarlyMediaAcousticMonitor();
    $pcm = str_repeat("\0", 320);
    $r0 = getrusage();
    $t = hrtime(true);
    for ($i = 0;$i < $n;$i++) {
        $mon->push($pcm);
    }$elapsed = (hrtime(true) - $t) / 1e9;
    $r1 = getrusage();
    echo json_encode(['scenario' => $scenario,'pid' => getmypid(),'frames' => $n,'cpu' => cpu($r1) - cpu($r0),'wall' => $elapsed,'metrics' => $mon->metrics(),'hash' => hash_file('sha256', $root.'/plugins/Utils/audio/EarlyMediaAcousticMonitor.php')])."\n";
    exit;
}
Co\run(function () use ($scenario, $n, $root) {
    $s = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $s->bind('127.0.0.1', 0);
    $tx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $tx->bind('127.0.0.1', 0);
    $dst = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $dst->bind('127.0.0.1', 0);
    $m = new MediaChannel($s, 'bench');
    $m->setNativeRtpReceive(true);
    $m->setAudioMetricsEnabled(true);
    $m->setSettings(['sendSilenceProbeToMembers' => false]);
    $add = function ($port, $codec, $leg) use ($m, $scenario) {
        $m->addMember(['address' => '127.0.0.1','port' => $port,'codec' => $codec,'pt' => $codec === 'PCMU' ? 0 : 8,'frequency' => 8000,'leg' => $leg,'ptime' => ($scenario === 'ptime10' ? 10 : ($scenario === 'ptime40' ? 40 : 20))]);
    };
    $add($tx->getsockname()['port'], 'PCMA', 'b');
    if ($scenario !== 'one') {
        $add($dst->getsockname()['port'], $scenario === 'transcode' ? 'PCMU' : 'PCMA', 'a');
    }
    $mon = new EarlyMediaAcousticMonitor();
    $tap = new EarlyMediaAcousticTap($mon);
    if ($scenario !== 'noacoustic') {
        $m->setEarlyMediaAcousticTap($tap);
    }
    $rx = 0;
    $out = 0;
    $dtmf = 0;
    $lat = [];
    $sent = [];
    $m->onReceive(function ($p) use (&$rx) {
        $rx++;
    });
    $m->onDtmf(function () use (&$dtmf) {
        $dtmf++;
    });
    $drain = true;
    Co::create(function () use ($dst, &$out, &$drain, &$lat, &$sent) {
        while ($drain) {
            $peer = [];
            $p = $dst->recvfrom($peer, .05);
            if (is_string($p)) {
                $out++;
                $seq = unpack('n', substr($p, 2, 2))[1];
                $lat[] = hrtime(true);
            }
        }
    });
    $m->start();
    Co::sleep(.01);
    $r0 = getrusage();
    $t0 = hrtime(true);
    for ($i = 0;$i < $n;$i++) {
        $isDtmf = $scenario === 'dtmf' && $i >= 20 && $i < 23;
        $payload = $isDtmf ? pack('CCn', 5, 138, 1280) : str_repeat(chr(213), 160);
        $tx->sendto('127.0.0.1', $s->getsockname()['port'], pack('CCnNN', 128, $isDtmf ? 101 : 8, $i, $isDtmf ? 3200 : $i * 160, 42).$payload);
        Co::sleep(.02);
    }
    Co::sleep(.04);
    $r1 = getrusage();
    $wall = (hrtime(true) - $t0) / 1e9;
    $metrics = $tap->metrics();
    $relay = $m->getRelayMetrics();
    $am = $m->getAudioMetrics();
    $m->close();
    $drain = false;
    Co::sleep(.06);
    $tx->close();
    $dst->close();
    $interval = [];
    for ($j = 1;$j < count($lat);$j++) {
        $interval[] = ($lat[$j] - $lat[$j - 1]) / 1e6;
    }sort($interval);
    echo json_encode(['scenario' => $scenario,'pid' => getmypid(),'binary' => PHP_BINARY,'swoole' => phpversion('swoole'),'method' => 'setNativeRtpReceive(true)','file' => (new ReflectionClass($m))->getFileName(),'source_hash' => hash_file('sha256', $root.'/plugins/Utils/sip/MediaChannel.php'),'monitor_hash' => hash_file('sha256', $root.'/plugins/Utils/audio/EarlyMediaAcousticMonitor.php'),'sent' => $n,'rx_audio' => $rx,'out' => $out,'dtmf_callbacks' => $dtmf,'rtp_metrics' => $am,'cpu' => cpu($r1) - cpu($r0),'wall' => $wall,'cpu_pct' => 100 * (cpu($r1) - cpu($r0)) / $wall,'cpu_us_per_input' => 1e6 * (cpu($r1) - cpu($r0)) / $n,'nvcsw' => $r1['ru_nvcsw'] - $r0['ru_nvcsw'],'nivcsw' => $r1['ru_nivcsw'] - $r0['ru_nivcsw'],'maxrss_kib' => $r1['ru_maxrss'],'zend_peak' => memory_get_peak_usage(true),'receive_interval_p50_ms' => $interval ? $interval[(int)(count($interval) * .5)] : null,'receive_interval_p99_ms' => $interval ? $interval[min(count($interval) - 1, (int)(count($interval) * .99))] : null,'acoustic' => $metrics,'relay' => $relay])."\n";
});
