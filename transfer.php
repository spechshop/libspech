<?php
use libspech\Cli\cli;
include (is_dir('libspech' ? 'libspech/' : ''))."plugins/autoloader.php";;

\co\run(function () {
    $fileConnections = file_get_contents('/home/lotus/projetos/spechshop-discadora/connections.json');
    $connections = json_decode($fileConnections, true);
    $transferTo = 'lotus';
    if (!isset($connections[$transferTo])) {
        return \libspech\Cli\cli::pcl("Transfer destination '$transferTo' not found in connections.json", 'bold_red');
    }


    $address = $connections[$transferTo]['address'];
    $port = $connections[$transferTo]['port'];


    $phone = new \libspech\Sip\trunkController($transferTo, '', $address, $port);
    $phone->setCallerId('discadora');
    $phone->mountLineCodecSDP('PCMA/8000');
    $phone->defineAudioFile('extra/assets/music.wav');


    $phone->onAnswer(function (\libspech\Sip\trunkController $phone) {
        $phone->receiveMedia();
        $phone->defineAudioFile('extra/assets/music.wav');
        \Swoole\Coroutine::sleep(3);
        $phone->send2833('9');
    });
    $phone->onFailed(function ($message) {
        return false;
    });
    $phone->onHangup(function () {
        cli::pcl("Chamada encerrada", "bold_red");
    });
    $phone->onKeyPress(function ($event, $peer) {
        cli::pcl("DTMF: " . $event, 'bold_green');
    });

    $phone->call('lotus');



});