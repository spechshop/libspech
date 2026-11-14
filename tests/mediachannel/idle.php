<?php

$root = realpath($argv[1]);
chdir($root);
require $root . '/plugins/autoloader.php';
use libspech\Rtp\MediaChannel;
use Swoole\Coroutine as Co;

Co\run(function (): void {
    $socket = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $socket->bind('127.0.0.1', 0);
    $media = new MediaChannel($socket, 'idle-cpu');
    $media->setNativeRtpReceive(false);
    $start = getrusage();
    $media->start();
    Co::sleep(1);
    $end = getrusage();
    $media->close();
    $cpuUs = ($end['ru_utime.tv_sec'] - $start['ru_utime.tv_sec'] + $end['ru_stime.tv_sec'] - $start['ru_stime.tv_sec']) * 1000000
        + $end['ru_utime.tv_usec'] - $start['ru_utime.tv_usec'] + $end['ru_stime.tv_usec'] - $start['ru_stime.tv_usec'];
    echo json_encode(['cpu_us' => $cpuUs, 'nvcsw' => $end['ru_nvcsw'] - $start['ru_nvcsw'],
        'nivcsw' => $end['ru_nivcsw'] - $start['ru_nivcsw'], 'zend_peak' => memory_get_peak_usage(true)]) . "\n";
});
