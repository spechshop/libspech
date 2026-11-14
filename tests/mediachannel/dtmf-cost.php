<?php

$root = realpath($argv[1]);
chdir($root);
require $root . '/plugins/autoloader.php';
use libspech\Rtp\{MediaChannel, rtpc};
use Swoole\Coroutine as Co;

Co\run(function (): void {
    $socket = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $socket->bind('127.0.0.1', 0);
    $media = new MediaChannel($socket, 'dtmf-cost');
    $callbacks = 0;
    $media->onDtmf(function () use (&$callbacks): void {
        $callbacks++;
    });
    $method = new ReflectionMethod($media, 'processDtmf');
    $start = getrusage();
    $time = hrtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $packet = new rtpc(pack('CCnNNCCn', 128, 101, $i, $i * 1600, 42, $i % 16, 138, 160));
        $method->invoke($media, $packet, ['address' => '127.0.0.1', 'port' => 9], static function (): void {
        });
    }
    $elapsed = hrtime(true) - $time;
    $end = getrusage();
    $cpuUs = ($end['ru_utime.tv_sec'] - $start['ru_utime.tv_sec'] + $end['ru_stime.tv_sec'] - $start['ru_stime.tv_sec']) * 1000000
        + $end['ru_utime.tv_usec'] - $start['ru_utime.tv_usec'] + $end['ru_stime.tv_usec'] - $start['ru_stime.tv_usec'];
    echo json_encode(['events' => 1000, 'callbacks' => $callbacks, 'cpu_us_per_event' => $cpuUs / 1000,
        'wall_ns_per_event' => $elapsed / 1000, 'zend_peak' => memory_get_peak_usage(true)]) . "\n";
    $media->close();
});
