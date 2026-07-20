<?php

use libspech\Cli\cli;
use function libspech\Sip\calculatePcmFrequency;

include 'plugins/autoloader.php';
Co\run(function () {
    $nameFile = 'rec_postal.wav';
    $indexData = 0;
    $extract = \libspech\Sip\wavChunks($nameFile);
    foreach ($extract as $t) {
        if ($t['id'] == 'data') {
            $indexData = $t['data'];
            break;
        }
    }
    $data = substr(file_get_contents($nameFile), $indexData);
    $split = str_split($data, 8000);
    $outsave = new \Swoole\StringObject("Gerado a partir do arquivo: $nameFile\n");
    \libspech\Cache\cache::define('time', microtime(true));
    foreach ($split as $chunk) {
        \Swoole\Coroutine::sleep(0.5);
        $frequency = calculatePcmFrequency($chunk, 8000);
        $currentTime = microtime(true);
        $elapsedTime = $currentTime - \libspech\Cache\cache::get('time');
        $elapsedMiliseconds = round($elapsedTime * 1000);
        $debug = "Frequência dominante: " . $frequency . " Hz (" . $elapsedMiliseconds . " ms) " . "25 Packets = " . 25 * 20 . "ms";
        $outsave->append($debug . PHP_EOL);
        cli::pcl($debug, "blue");
    }
    \Swoole\Coroutine::writeFile('outsave.txt', $outsave->toString());
});