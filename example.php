<?php

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
    $phone->mountLineCodecSDP('L16/8000');


    $phone->onRinging(function ($call) {

    });

    $phone->onHangup(function (trunkController $phone) use (&$audioBuffer) {

        \Plugin\Utils\cli::pcl("Chamada finalizada");
        $pcm = $phone->bufferAudio;
        $phone->saveBufferToWavFile('audio.wav', $pcm);

    });


    $phone->onAnswer(function (trunkController $phone) {
        $phone->receiveMedia();
        \Plugin\Utils\cli::pcl("Chamada aceita", "green");
        \Swoole\Coroutine::sleep(10);
        $phone->send2833('*');

        \Swoole\Coroutine::sleep(10);

        $phone->socket->sendto($phone->host, $phone->port, sip::renderSolution(
            \handlers\renderMessages::generateBye($phone->headers200['headers'])
        ));

    });


    $phone->onReceiveAudio(function ($pcmData, $peer, trunkController $phone) {
        $phone->bufferAudio .= $pcmData;
    });


    $phone->prefix = 4479;
    $phone->call('5569984999999');


});