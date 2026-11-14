<?php

ini_set('memory_limit', '1024M');
$root = realpath($argv[1]);
chdir($root);
require $root.'/plugins/autoloader.php';
use libspech\Rtp\MediaChannel;
use Swoole\Coroutine as Co;

Co\run(function () {
    $s = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $s->bind('127.0.0.1', 0);
    $tx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $tx->bind('127.0.0.1', 0);
    $d = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $d->bind('127.0.0.1', 0);
    $m = new MediaChannel($s, 'flows');
    $m->setSettings(['sendSilenceProbeToMembers' => false]);
    $m->setAudioMetricsEnabled(true);
    $source = '127.0.0.1:'.$tx->getsockname()['port'];
    $dest = '127.0.0.1:'.$d->getsockname()['port'];
    foreach ([[$tx,'b'],[$d,'a']] as [$sock,$leg]) {
        $m->addMember(['address' => '127.0.0.1','port' => $sock->getsockname()['port'],'codec' => 'PCMA','pt' => 8,'frequency' => 8000,'leg' => $leg]);
    }
    $seen = [];
    $m->onReceive(function ($p) use (&$seen) {
        $seen[] = $p->sequence;
    });
    $m->start();
    $port = $s->getsockname()['port'];
    $tx->sendto('127.0.0.1', $port, pack('CCnN', 128, 200, 1, 42));
    Co::sleep(.005);
    foreach ([1,3,2,4] as $seq) {
        $tx->sendto('127.0.0.1', $port, pack('CCnNN', 128, 8, $seq, $seq * 160, 42).str_repeat(chr(213), 160));
        $peer = [];
        if (!is_string($d->recvfrom($peer, .2))) {
            throw new Exception('relay missing');
        }
    }
    Co::sleep(.01);
    $metrics = $m->getAudioMetrics();
    $ok = $seen === [1,3,2,4] && $metrics['rtcp_packets'] === 1;
    $m->rebindMemberTransport('a', '127.0.0.1', 54321);
    $rebind = $m->memberByLeg('a')['port'] === 54321;
    unset($m->members['127.0.0.1:54321']);
    $removed = !$m->isMember('127.0.0.1:54321');
    $m->close();
    $m->close();
    Co::sleep(.02);
    $closed = $s->isClosed() && $m->members === [];
    echo json_encode(['rtcp_reorder_relay' => $ok,'rebind' => $rebind,'public_removal' => $removed,'closed' => $closed,'metrics' => $metrics])."\n";
    $tx->close();
    $d->close();
    if (!$ok || !$rebind || !$removed || !$closed) {
        throw new RuntimeException('flow contract failed');
    }
});
