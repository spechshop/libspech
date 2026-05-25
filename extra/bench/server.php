<?php

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpc;
use libspech\Rtp\rtpChannel;
use Swoole\Coroutine;

include '../../plugins/autoloader.php';

gc_disable();

$options = getopt('', [
    'bind-ip::',
    'public-ip::',
    'control-port::',
    'grace::',
]);

$bindIp      = (string)($options['bind-ip'] ?? '0.0.0.0');
$publicIp    = (string)($options['public-ip'] ?? '');
$controlPort = (int)($options['control-port'] ?? 39000);
$grace        = (int)($options['grace'] ?? 3);

function nowNs(): int
{
    return hrtime(true);
}

function sendControlJson(SocketMutable $sock, string $address, int $port, array $data): void
{
    $sock->sendto($address, $port, json_encode($data, JSON_UNESCAPED_SLASHES) . "\n");
}

function addBenchMember(
    MediaChannel $mediaChannel,
    string $address,
    int $port,
    string $codec = 'PCMA',
    int $pt = 8,
    int $frequency = 8000,
    int $channels = 1,
    ?int $ssrc = null
): int {
    $idFrom = "{$address}:{$port}";
    $ssrc = $ssrc ?? $mediaChannel->generateDeterministicSsrc($idFrom);

    $mediaChannel->addMember([
        'address' => $address,
        'port' => $port,
        'codec' => $codec,
        'pt' => $pt,
        'timestamp' => time(),
        'config' => [],
        'ssrc' => $ssrc,
        'frequency' => $frequency,
        'channels' => $channels,
    ]);

    return $ssrc;
}

function startMediaBench(
    SocketMutable $controlSock,
    array $session,
    string $publicIp,
    int $grace,
    bool &$finished
): void {
    Coroutine::create(function () use ($controlSock, $session, $publicIp, $grace, &$finished) {
        $channelsCount = (int)$session['channels'];
        $seconds = (int)$session['seconds'];
        $legs = (int)$session['legs'];
        $ptimeMs = (int)$session['ptime'];
        $sessionId = (string)$session['session'];

        $peerA = $session['peerA'];
        $peerB = $session['peerB'] ?? null;

        $stats = [
            'on_receive' => 0,
            'first_packet_time' => null,
        ];

        $mediaChannels = [];
        $ports = [];

        echo "\nCriando MediaChannels aleatorias...\n";
        echo "session={$sessionId}, channels={$channelsCount}, legs={$legs}, seconds={$seconds}, ptime={$ptimeMs}ms\n";
        echo "peerA={$peerA['address']}:{$peerA['port']}\n";
        if ($peerB !== null) {
            echo "peerB={$peerB['address']}:{$peerB['port']}\n";
        }
        echo "\n";

        $ssrcA = null;
        $ssrcB = null;

        for ($i = 0; $i < $channelsCount; $i++) {
            $sock = new SocketMutable(AF_INET, SOCK_DGRAM, 0);
            $sock->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
            $sock->setOption(SOL_SOCKET, SO_RCVBUF, 4 * 1024 * 1024);
            $sock->setOption(SOL_SOCKET, SO_SNDBUF, 4 * 1024 * 1024);

            if (!$sock->bind('0.0.0.0', 0)) {
                echo "Falha ao bindar MediaChannel {$i}\n";
                continue;
            }

            $localPort = (int)$sock->getsockname()['port'];
            $mediaChannel = new MediaChannel($sock, "bench-{$sessionId}-{$i}");
            $mediaChannel->connectTimeout=1;

            $currentSsrcA = addBenchMember(
                $mediaChannel,
                $peerA['address'],
                (int)$peerA['port'],
                'PCMA',
                8,
                8000,
                1
            );

            if ($ssrcA === null) {
                $ssrcA = $currentSsrcA;
            }

            if ($legs >= 2 && $peerB !== null) {
                $currentSsrcB = addBenchMember(
                    $mediaChannel,
                    $peerB['address'],
                    (int)$peerB['port'],
                    'PCMA',
                    8,
                    8000,
                    1
                );

                if ($ssrcB === null) {
                    $ssrcB = $currentSsrcB;
                }
            }

            $mediaChannel->onReceive(function (
                rtpc $rtpc,
                array $peer,
                MediaChannel $channel,
                rtpChannel $rtpChannel
            ) use (&$stats) {
                $stats['on_receive']++;

                if ($stats['first_packet_time'] === null) {
                    $stats['first_packet_time'] = microtime(true);
                    echo "Primeiro RTP recebido de {$peer['address']}:{$peer['port']}\n";
                }
            });

            $mediaChannels[] = [
                'channel' => $mediaChannel,
                'socket' => $sock,
                'port' => $localPort,
            ];

            $ports[] = $localPort;

            Coroutine::create(function () use ($mediaChannel) {
                $mediaChannel->start();
                $mediaChannel?->block();
            });
        }

        $ready = [
            'cmd' => 'ready',
            'session' => $sessionId,
            'ip' => $publicIp,
            'ports' => $ports,
            'channels' => count($ports),
            'legs' => $legs,
            'seconds' => $seconds,
            'ptime' => $ptimeMs,
            'payloadType' => 8,
            'codec' => 'PCMA',
            'ssrcA' => $ssrcA,
            'ssrcB' => $ssrcB,
        ];

        // Envia algumas vezes para reduzir chance de perder o READY em UDP.
        for ($r = 0; $r < 5; $r++) {
            sendControlJson($controlSock, $peerA['address'], (int)$peerA['port'], $ready);

            if ($peerB !== null) {
                sendControlJson($controlSock, $peerB['address'], (int)$peerB['port'], $ready);
            }

            Coroutine::sleep(0.05);
        }

        echo "READY enviado. Portas RTP abertas: " . count($ports) . "\n\n";

        $start = nowNs();
        $last = $start;
        $lastReceive = 0;
        $runSeconds = $seconds + $grace;

        while (((nowNs() - $start) / 1_000_000_000) < $runSeconds) {
            Coroutine::sleep(1);

            $now = nowNs();
            $elapsed = ($now - $start) / 1_000_000_000;
            $delta = ($now - $last) / 1_000_000_000;

            $recvPps = ($stats['on_receive'] - $lastReceive) / max(0.001, $delta);

            printf(
                "t=%5.1fs | channels=%4d | onReceive=%9.0fpps | total=%9d | mem=%7.2fMB\n",
                $elapsed,
                count($ports),
                $recvPps,
                $stats['on_receive'],
                memory_get_usage(true) / 1024 / 1024
            );

            $last = $now;
            $lastReceive = $stats['on_receive'];
        }

        foreach ($mediaChannels as $item) {
            $channel = $item['channel'];
            $socket = $item['socket'];

            if (method_exists($channel, 'unblock')) {
                $channel->unblock();
            }

            if (method_exists($socket, 'close')) {
                $socket->close();
            }
        }

        Coroutine::sleep(0.2);

        $elapsed = (nowNs() - $start) / 1_000_000_000;
        $realRecvPps = $stats['on_receive'] / max(0.001, $elapsed);

        $final = [
            'cmd' => 'final',
            'session' => $sessionId,
            'onReceive' => $stats['on_receive'],
            'realReceivePps' => (int)round($realRecvPps),
            'memoryMb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];

        sendControlJson($controlSock, $peerA['address'], (int)$peerA['port'], $final);
        if ($peerB !== null) {
            sendControlJson($controlSock, $peerB['address'], (int)$peerB['port'], $final);
        }

        echo "\n=== RESULTADO FINAL VPS ===\n";
        echo "Session:           {$sessionId}\n";
        echo "Channels:          " . count($ports) . "\n";
        echo "Legs:              {$legs}\n";
        echo "Ptime:             {$ptimeMs}ms\n";
        echo "onReceive total:   {$stats['on_receive']}\n";
        echo "Real receive PPS:  " . number_format($realRecvPps, 0, '.', '') . "\n";
        echo "Memory final:      " . number_format(memory_get_usage(true) / 1024 / 1024, 2, '.', '') . "MB\n";

        $finished = true;
    });
}

Co\run(function () use ($bindIp, $publicIp, $controlPort, $grace) {
    $controlSock = new SocketMutable(AF_INET, SOCK_DGRAM, 0);
    $controlSock->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
    $controlSock->setOption(SOL_SOCKET, SO_RCVBUF, 4 * 1024 * 1024);
    $controlSock->setOption(SOL_SOCKET, SO_SNDBUF, 4 * 1024 * 1024);

    if (!$controlSock->bind($bindIp, $controlPort)) {
        echo "Falha ao bindar controle UDP em {$bindIp}:{$controlPort}\n";
        return;
    }

    echo "VPS Discovery Engine UDP iniciado\n";
    echo "controle={$bindIp}:{$controlPort}\n";
    echo "public-ip=" . ($publicIp !== '' ? $publicIp : '(cliente usa --vps-ip como destino)') . "\n\n";

    $sessions = [];
    $started = false;
    $finished = false;

    Coroutine::create(function () use ($controlSock, $publicIp, $grace, &$sessions, &$started, &$finished) {
        while (!$finished) {
            $peer = [];
            $packet = $controlSock->recvfrom($peer, 65535);

            if ($packet === false || $packet === '') {
                Coroutine::sleep(0.001);
                continue;
            }

            $msg = json_decode(trim($packet), true);

            if (!is_array($msg)) {
                continue;
            }

            $cmd = (string)($msg['cmd'] ?? '');

            if ($cmd !== 'connect') {
                continue;
            }

            $sessionId = (string)($msg['session'] ?? 'default');
            $leg = strtoupper((string)($msg['leg'] ?? 'A'));

            if (!isset($sessions[$sessionId])) {
                $sessions[$sessionId] = [
                    'session' => $sessionId,
                    'channels' => max(1, (int)($msg['channels'] ?? 50)),
                    'seconds' => max(1, (int)($msg['seconds'] ?? 30)),
                    'legs' => max(1, min(2, (int)($msg['legs'] ?? 2))),
                    'ptime' => max(1, (int)($msg['ptime'] ?? 20)),
                    'peerA' => null,
                    'peerB' => null,
                ];
            }

            $sessions[$sessionId]['channels'] = max(1, (int)($msg['channels'] ?? $sessions[$sessionId]['channels']));
            $sessions[$sessionId]['seconds'] = max(1, (int)($msg['seconds'] ?? $sessions[$sessionId]['seconds']));
            $sessions[$sessionId]['legs'] = max(1, min(2, (int)($msg['legs'] ?? $sessions[$sessionId]['legs'])));
            $sessions[$sessionId]['ptime'] = max(1, (int)($msg['ptime'] ?? $sessions[$sessionId]['ptime']));

            $learnedPeer = [
                'address' => $peer['address'],
                'port' => (int)$peer['port'],
            ];

            if ($leg === 'B') {
                $sessions[$sessionId]['peerB'] = $learnedPeer;
            } else {
                $sessions[$sessionId]['peerA'] = $learnedPeer;
            }

            echo "CONNECT {$sessionId} leg={$leg} from {$learnedPeer['address']}:{$learnedPeer['port']}\n";

            $s = $sessions[$sessionId];
            $readyToStart = $s['peerA'] !== null && ($s['legs'] < 2 || $s['peerB'] !== null);

            if ($readyToStart && !$started) {
                $started = true;
                startMediaBench($controlSock, $s, $publicIp, $grace, $finished);
            }
        }

        $controlSock->close();
    });

    while (!$finished) {
        Coroutine::sleep(0.2);
    }
});