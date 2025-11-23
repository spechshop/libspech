<?php


use libspech\Cli\cli;
use libspech\Packet\renderMessages;
use libspech\Sip\sip;
use libspech\Sip\trunkController;

include 'plugins/autoloader.php';


\Swoole\Coroutine\run(function () {
    $username = 'lotus';
    $password = '';
    $domain = 'spechshop.com';
    $host = gethostbyname($domain);
    $phone = new trunkController(
        $username,
        $password,
        $host,
        5060,
    );
    if (!$phone->register(2)) {
        throw new \Exception("Erro ao registrar");
    }
    $audioBuffer = '';
    $phone->mountLineCodecSDP('G729/8000');


    $phone->onRinging(function ($call) {
        cli::pcl("Chamada recebida", "yellow");
    });

    $phone->onHangup(function (trunkController $phone) use (&$audioBuffer) {

        cli::pcl("Chamada finalizada");
        $pcm = $phone->bufferAudio;
        $phone->saveBufferToWavFile('audio.wav', $pcm);
        \Swoole\Coroutine::sleep(1);
        cli::pcl("Áudio salvo em audio.wav com " . strlen(file_get_contents('audio.wav')) . " bytes", "yellow");

    });


    $phone->onAnswer(function (trunkController $phone) {
        $phone->receiveMedia();
        cli::pcl("Chamada aceita", "green");
        \Swoole\Coroutine::sleep(12);
        $phone->send2833('47883324268', 100);

        \Swoole\Coroutine::sleep(30);

        $phone->socket->sendto($phone->host, $phone->port, sip::renderSolution(
            renderMessages::generateBye($phone->headers200['headers'])
        ));

    });


    $phone->onReceiveAudio(function ($pcmData, $peer, trunkController $phone) {
        $phone->bufferAudio .= $pcmData;
    });


    $phone->prefix = 4479;
    $phone->call('551140040104');


});