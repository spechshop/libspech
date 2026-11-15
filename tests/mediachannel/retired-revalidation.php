<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/plugins/autoloader.php';

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpc;
use Swoole\Coroutine;
use Swoole\Coroutine\Socket;

function retiredTestCheck(bool $ok, string $reason): void
{
    if (!$ok) throw new RuntimeException($reason);
}

function retiredTestSocket(): Socket
{
    $s = new Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
    retiredTestCheck($s->bind('127.0.0.1', 0), 'test UDP bind failed');
    return $s;
}

function retiredTestRtp(int $seq, int $ssrc=12345): string
{
    return pack('CCnNN', 0x80, 8, $seq, $seq * 160, $ssrc)
        . str_repeat("\xd5", 160);
}

Coroutine\run(static function(): void {
    foreach ([false, true] as $native) {
        $worker = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
        retiredTestCheck($worker->bind('127.0.0.1', 0), 'media socket bind failed');
        $port = (int)$worker->getsockname()['port'];
        $old = retiredTestSocket();
        $new = retiredTestSocket();
        $other = retiredTestSocket();
        $oldId = '127.0.0.1:' . $old->getsockname()['port'];
        $newId = '127.0.0.1:' . $new->getsockname()['port'];
        $media = new MediaChannel($worker, 'retired-revalidation-' . (int)$native);
        $media->setNativeRtpReceive($native);
        $media->setSettings(['sendSilenceProbeToMembers' => false]);
        $media->addMember([
            'address' => '127.0.0.1', 'port' => $old->getsockname()['port'],
            'codec' => 'PCMA', 'pt' => 8, 'frequency' => 8000, 'leg' => 'a',
        ]);
        $media->addMember([
            'address' => '127.0.0.1', 'port' => $other->getsockname()['port'],
            'codec' => 'PCMA', 'pt' => 8, 'frequency' => 8000, 'leg' => 'b',
        ]);
        $media->start();
        try {
            retiredTestCheck($media->rebindMemberTransport('a', '127.0.0.1', $new->getsockname()['port']),
                'initial rebind failed');
            retiredTestCheck(!isset($media->members[$oldId]), 'old peer not retired');
            $old->sendto('127.0.0.1', $port, retiredTestRtp(10));
            Coroutine::sleep(.04);
            retiredTestCheck(!isset($media->members[$oldId]), 'retired peer auto-admitted without callback');
            retiredTestCheck(($media->memberByLeg('a')['port'] ?? null) === $new->getsockname()['port'],
                'retired peer changed active endpoint without callback');
            $from = [];
            retiredTestCheck($other->recvfrom($from, .03) === false,
                'retired peer injected audio without validator');

            $observed = 0;
            $media->onUnknownEndpoint(function (rtpc $packet, array $peer, MediaChannel $channel) use (&$observed, $oldId): void {
                if ($peer['address'] . ':' . $peer['port'] !== $oldId) return;
                $observed++;
                if ($observed >= 3) {
                    retiredTestCheck($channel->rebindMemberTransport('a', $peer['address'], $peer['port']),
                        'explicit callback rebind failed');
                }
            });
            for ($i=0; $i<3; $i++) {
                $old->sendto('127.0.0.1', $port, retiredTestRtp(30+$i));
                Coroutine::sleep(.04);
                if ($i<2) {
                    retiredTestCheck(!isset($media->members[$oldId]),
                        'retired member was prematurely admitted');
                    retiredTestCheck($other->recvfrom($from, .025) === false,
                        'unconfirmed retired RTP entered media pipeline');
                }
            }
            retiredTestCheck($observed===3, 'callback did not inspect retired RTP');
            retiredTestCheck(($media->memberByLeg('a')['port']??null)===$old->getsockname()['port'],
                'callback-approved retired endpoint was not restored');
            retiredTestCheck(isset($media->members[$oldId])&&!isset($media->members[$newId]),
                'rebind did not retire the previously active endpoint');
            retiredTestCheck(is_string($other->recvfrom($from, .3)),
                'confirmed retired endpoint did not re-enter media pipeline');
            echo 'PASS: retired endpoint admission is callback-gated, native=' . (int)$native . "\n";
        } finally {
            $media->close();
            $old->close(); $new->close(); $other->close();
        }
    }
});
