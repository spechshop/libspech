<?php


use libspech\Cli\cli;
use function libspech\Sip\calculatePcmFrequency;

include 'plugins/autoloader.php';

Co\run(function (){

    $nameFile = 'rec.wav';


    $indexData=0;
    $extract = \libspech\Sip\wavChunks($nameFile);
    foreach ($extract as $t) {
        if ($t['id'] == 'data') {
            $indexData=$t['data'];
            break;
        }
    }
    $data=substr(file_get_contents($nameFile), $indexData);


    $split = str_split($data, 8000);



    \libspech\Cache\cache::define('time', microtime(true));
    foreach ($split as $chunk) {
        co::sleep(0.5);
        $frequency = calculatePcmFrequency($chunk, 8000);
        //$frequency = 0;
        $currentTime = microtime(true);
        $elapsedTime = $currentTime - \libspech\Cache\cache::get('time');
        // formatar em milisegundos
        $elapsedMiliseconds = round($elapsedTime * 1000);


        cli::pcl("Frequência dominante: "
            . $frequency . " Hz (" . $elapsedMiliseconds . " ms) "
            ."25 Packets = ".
            (25* 20).
            "ms"
            , "blue");




    }
});