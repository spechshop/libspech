<?php

ini_set('memory_limit', '1024M');
$root = realpath($argv[1]);
chdir($root);
require $root.'/plugins/autoloader.php';
use libspech\audio\EarlyMediaAcousticMonitor;

$results = [];
foreach (['aligned','fragmented','multi'] as $mode) {
    $m = new EarlyMediaAcousticMonitor();
    $pcm = '';
    for ($i = 0;$i < 16000;$i++) {
        $pcm .= pack('v', (int)(7000 * sin(2 * M_PI * 425 * $i / 8000)) & 65535);
    }
    $chunk = match($mode) {
        'aligned' => 320,'fragmented' => 137,'multi' => 960
    };
    for ($i = 0;$i < strlen($pcm);$i += $chunk) {
        $m->push(substr($pcm, $i, $chunk));
    }
    $metrics = $m->metrics();
    unset($metrics['processing_ns']);
    $events = $m->takeEvents();
    foreach ($events as &$e) {
        unset($e['monotonic_ns']);
    }unset($e);
    $results[$mode] = ['metrics' => $metrics,'events' => $events];
}
echo json_encode($results)."\n";
