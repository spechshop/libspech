<?php

ini_set('memory_limit', '1024M');
$root = realpath($argv[1]);
chdir($root);
require $root.'/plugins/autoloader.php';
use libspech\Rtp\MediaChannel;
use Swoole\Coroutine as Co;

$pass = true;
Co\run(function () use (&$pass) {
    foreach (['PCMA' => 8,'PCMU' => 0] as $codec => $pt) {
        $s = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
        $s->bind('127.0.0.1', 0);
        $tx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
        $tx->bind('127.0.0.1', 0);
        $d = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
        $d->bind('127.0.0.1', 0);
        $m = new MediaChannel($s, 'ptime-frame');
        $m->setSettings(['sendSilenceProbeToMembers' => false]);
        foreach ([$tx,$d] as $sock) {
            $m->addMember(['address' => '127.0.0.1','port' => $sock->getsockname()['port'],'codec' => $codec,'pt' => $pt,'frequency' => 8000,'ptime' => 20]);
        }$m->start();
        $tx->sendto('127.0.0.1', $s->getsockname()['port'], pack('CCnNN', 128, $pt, 1, 0, 42).str_repeat(chr($pt === 8 ? 213 : 255), 320));
        $packets = [];
        $peer = [];
        while (($p = $d->recvfrom($peer, .06)) !== false) {
            $packets[] = $p;
        }$lengths = array_map(fn ($p) => strlen($p) - 12, $packets);
        $ok = $lengths === [160,160];
        $pass = $pass && $ok;
        echo json_encode(compact('codec', 'lengths', 'ok'))."\n";
        $m->close();
        Co::sleep(.01);
        $tx->close();
        $d->close();
    }
});
exit($pass ? 0 : 1);
