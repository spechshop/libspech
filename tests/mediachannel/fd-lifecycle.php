<?php
ini_set('memory_limit', '1024M');
$root = realpath($argv[1]);
chdir($root);
require $root.'/plugins/autoloader.php';
use libspech\Rtp\MediaChannel;
use Swoole\Coroutine as Co;

function fdSnapshot(): array {
    $out = [];
    foreach (glob('/proc/self/fd/*') ?: [] as $p) {
        $target = @readlink($p);
        if ($target !== false) $out[basename($p)] = $target;
    }
    return $out;
}
function usageDelta(array $before): array {
    $now = getrusage(); $out = [];
    foreach (['utime', 'stime'] as $kind) {
        $out[$kind.'_us'] = ($now['ru_'.$kind.'.tv_sec'] - $before['ru_'.$kind.'.tv_sec']) * 1000000
            + $now['ru_'.$kind.'.tv_usec'] - $before['ru_'.$kind.'.tv_usec'];
    }
    foreach (['nvcsw', 'nivcsw'] as $key) $out[$key] = $now['ru_'.$key] - $before['ru_'.$key];
    return $out;
}
$native = ($argv[2] ?? 'traditional') === 'native';
if (!is_dir('/proc/self/fd') || ($native && (!method_exists(SocketMutable::class, 'recvfromInto') || !class_exists(Swoole\Buffer::class)))) {
    fwrite(STDERR, "Cannot certify FD lifecycle: procfs/native API unavailable\n"); exit(1);
}
if ($native && (!function_exists('psampler_binary_slice') || !ByteBuffer::supportsNativeBuffer(new Swoole\Buffer(65536)))) {
    fwrite(STDERR, "Cannot certify native receive: native buffer integration unavailable\n"); exit(1);
}
$rows = []; $failures = [];
Co\run(function () use ($native, &$rows, &$failures): void {
    Co::sleep(.001); // Initialize permanent reactor/timer resources before baseline.
    $reactorBaseline = fdSnapshot();
    $guard = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $guard->bind('127.0.0.1', 0);
    $guardChannel = new MediaChannel($guard, 'live-guard');
    $guardChannel->setNativeRtpReceive($native);
    $guardChannel->setReceiveOnlyMode(true);
    $guardChannel->connectTimeout = 30;
    $guardSeen = 0;
    $guardChannel->onReceive(function () use (&$guardSeen): void { $guardSeen++; });
    $guardChannel->start();
    $baseCoroutines = Co::stats()['coroutine_num'];
    $guardTarget = @readlink('/proc/self/fd/'.$guard->fd);
    foreach (['unstarted', 'blocked', 'traffic', 'timeout', 'reentrant', 'active'] as $scenario) {
        foreach ([1, 4, 16, 50] as $count) {
            $before = fdSnapshot(); $usage = getrusage(); $start = hrtime(true);
            $channels = []; $sockets = []; $owned = []; $cids = []; $weak = [];
            $callbacks = 0;
            for ($i = 0; $i < $count; $i++) {
                // Constructor and start run in a different coroutine from close().
                Co::create(function () use (&$channels, &$sockets, &$owned, &$cids, &$weak, &$callbacks, $i, $native, $scenario): void {
                    $s = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
                    if (!$s->bind('127.0.0.1', 0)) throw new RuntimeException('bind failed');
                    $m = new MediaChannel($s, "fd-$scenario-$i");
                    $m->setNativeRtpReceive($native);
                    $m->setReceiveOnlyMode($scenario !== 'active');
                    $m->connectTimeout = $scenario === 'timeout' ? 0 : 5;
                    $channels[] = $m; $sockets[] = $s; $weak[] = WeakReference::create($m);
                    foreach ([$s, $m->eventSock] as $socket) {
                        $target = @readlink('/proc/self/fd/'.$socket->fd);
                        if (!is_string($target) || !str_starts_with($target, 'socket:[')) throw new RuntimeException('socket identity unavailable');
                        $owned[] = $target;
                    }
                    $m->onReceive(function ($packet, $peer, $channel) use (&$callbacks, $scenario): void {
                        $callbacks++;
                        if ($scenario === 'reentrant') { $channel->close(); $channel->close(); }
                    });
                    if ($scenario === 'active') {
                        $m->setAudioMetricsEnabled(true);
                        $m->setEarlyMediaAcousticTap(new libspech\Rtp\EarlyMediaAcousticTap(new libspech\audio\EarlyMediaAcousticMonitor()));
                        $m->onDtmf(static function (): void {});
                        $m->addMember(['address'=>'127.0.0.1', 'port'=>9, 'codec'=>'PCMA', 'pt'=>8, 'frequency'=>8000, 'leg'=>'b']);
                    }
                    if ($scenario !== 'unstarted') {
                        $m->start();
                        $cids[] = $s->getBoundCid(SWOOLE_EVENT_READ);
                    }
                    if (in_array($scenario, ['traffic', 'reentrant', 'active'], true)) {
                        $tx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
                        $tx->sendto('127.0.0.1', $m->listenPort, pack('CCnNN',128,8,1,160,42).str_repeat(chr(213),160));
                        if ($scenario === 'active') {
                            $tx->sendto('127.0.0.1', $m->listenPort, pack('CCnNNCCn',128,101,2,320,42,5,138,160));
                        }
                        $tx->close();
                    }
                });
            }
            $opened = fdSnapshot();
            if ($scenario === 'timeout') Co::sleep(.03); else Co::sleep(.005);
            $closeUsage = getrusage(); $closeStart = hrtime(true);
            foreach ($channels as $m) { $m->close(); $m->close(); }
            unset($m);
            $closeCost = ['wall_us'=>(hrtime(true)-$closeStart)/1000] + usageDelta($closeUsage);
            $afterClose = fdSnapshot();
            Co::sleep(.01);
            $afterCoroutines = fdSnapshot();
            $states = array_map(fn($m) => ['active'=>$m->active,'rtp_closed'=>$m->socket->isClosed(), 'event_closed'=>$m->eventSock->isClosed()], $channels);
            $readers = array_values(array_filter($cids, fn($cid) => $cid > 0 && Co::exists($cid)));
            // A closed public socket may remain referenced by the caller/RtpSession.
            // Certification requires kernel release even in that supported situation.
            Co::sleep(.04);
            $heldAfter50ms = array_intersect(fdSnapshot(), $owned);
            $channels = []; $sockets = [];
            $afterObjects = fdSnapshot();
            // No gc_collect_cycles(): destruction and one deferred reactor pass suffice.
            Co::sleep(.01);
            $afterStabilization = fdSnapshot();
            $remaining = array_intersect($afterStabilization, $owned);
            $alive = count(array_filter($weak, fn($ref) => $ref->get() !== null));
            $guardOk = in_array($guardTarget, $afterStabilization, true);
            $allClosed = count(array_filter($states, fn($state) => $state['active'] || !$state['rtp_closed'] || !$state['event_closed'])) === 0;
            $pass = $allClosed && !$heldAfter50ms && !$remaining && !$readers && !$alive && $guardOk
                && Co::stats()['coroutine_num'] === $baseCoroutines;
            if (!$pass) $failures[] = "$scenario/$count";
            $rows[] = compact('scenario','count','before','opened','owned','afterClose','afterCoroutines','states','readers',
                'heldAfter50ms','afterObjects','afterStabilization','remaining','alive','guardOk','pass','closeCost')
                + ['elapsed_ms'=>(hrtime(true)-$start)/1e6, 'usage'=>usageDelta($usage),
                   'memory'=>memory_get_usage(), 'rss_kb'=>getrusage()['ru_maxrss'], 'callbacks'=>$callbacks,
                   'coroutines'=>Co::stats()['coroutine_num'], 'reactor_baseline'=>$reactorBaseline];
        }
    }
    // Verify the active channel after descriptor reuse across all cycles.
    $guard->sendto('127.0.0.1', $guard->getsockname()['port'], pack('CCnNN',128,8,1,160,99).str_repeat(chr(213),160));
    Co::sleep(.01);
    if ($guardSeen !== 1 || !$guardChannel->active) $failures[] = 'guard channel traffic';
    $guardChannel->close(); unset($guardChannel, $guard); Co::sleep(.01);
    $idleUsage = getrusage(); Co::sleep(.1);
    $rows[] = ['idle_100ms'=>usageDelta($idleUsage), 'inside_reactor_final'=>fdSnapshot()];
});
$json = json_encode(['php'=>PHP_VERSION,'swoole'=>SWOOLE_VERSION,'native'=>$native,'limit_ms'=>50,
    'rows'=>$rows,'failures'=>$failures,'after_reactor'=>fdSnapshot()], JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
if (isset($argv[3])) file_put_contents($argv[3], $json);
echo $json;
exit($failures ? 1 : 0);
