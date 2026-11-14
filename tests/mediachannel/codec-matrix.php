<?php

ini_set('memory_limit', '1024M');
$root = realpath($argv[1]);
chdir($root);
require $root.'/plugins/autoloader.php';
use libspech\Rtp\{MediaChannel,EarlyMediaAcousticTap};
use libspech\audio\EarlyMediaAcousticMonitor;
use Swoole\Coroutine as Co;

$all = true;
Co\run(function () use (&$all) {
    foreach ([['PCMA',8,8000],['PCMU',0,8000],['G729',18,8000],['GSM',3,8000],['OPUS',111,48000]] as [$codec,$pt,$rate]) {
        $s = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
        $s->bind('127.0.0.1', 0);
        $tx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
        $tx->bind('127.0.0.1', 0);
        $d = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
        $d->bind('127.0.0.1', 0);
        $m = new MediaChannel($s, 'matrix');
        $m->setSettings(['sendSilenceProbeToMembers' => false]);
        $m->addMember(['address' => '127.0.0.1','port' => $tx->getsockname()['port'],'codec' => $codec,'pt' => $pt,'frequency' => $rate,'ptime' => 20,'leg' => 'b']);
        $m->addMember(['address' => '127.0.0.1','port' => $d->getsockname()['port'],'codec' => 'PCMA','pt' => 8,'frequency' => 8000,'ptime' => 20,'leg' => 'a']);
        $mon = new EarlyMediaAcousticMonitor();
        $tap = new EarlyMediaAcousticTap($mon);
        $m->setEarlyMediaAcousticTap($tap);
        $m->start();
        $encoder = null;
        $pcm = str_repeat("\0", 320);
        $payload = match($codec) {
            'PCMA' => encodePcmToPcma($pcm),'PCMU' => encodePcmToPcmu($pcm),'G729' => ($encoder = new bcg729Channel())->encode(substr($pcm, 0, 160)).$encoder->encode(substr($pcm, 160)),'GSM' => ($encoder = new gsmChannel())->encode($pcm),'OPUS' => ($encoder = new opusChannel(48000, 1))->encode(str_repeat("\0", 1920))
        };
        $tx->sendto('127.0.0.1', $s->getsockname()['port'], pack('CCnNN', 128, $pt, 1, 0, 42).$payload);
        $peer = [];
        $received = $d->recvfrom($peer, .3);
        $ok = is_string($received) && strlen($received) === 172;
        $all = $all && $ok;
        echo json_encode(['codec' => $codec,'to' => 'PCMA','received_bytes' => is_string($received) ? strlen($received) : 0,'pass' => $ok,'tap' => $tap->metrics()])."\n";
        $timer = Swoole\Timer::after(10, function () use ($m) {
            $m->close();
        });
        Co::sleep(.03);
        $closed = !$m->active && $m->members === [];
        echo json_encode(['timer_teardown' => $closed,'codec' => $codec])."\n";
        $all = $all && $closed;
        $tx->close();
        $d->close();
        if ($encoder !== null) {
            if (method_exists($encoder, 'close')) {
                $encoder->close();
            } elseif (method_exists($encoder, 'destroy')) {
                $encoder->destroy();
            }
        }
    }
});
exit($all ? 0 : 1);
