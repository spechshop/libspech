<?php

/**
 * libspech - Example Usage
 *
 * Copyright (c) 2025 Lotus / berzersks
 * Website: https://spechshop.com
 * All Rights Reserved.
 *
 * PROPRIETARY SOFTWARE - Unauthorized use is prohibited.
 * Please respect the creator. See LICENSE for terms.
 */
ini_set('memory_limit', '1024M');

use libspech\Cli\cli;
use libspech\Sip\trunkController;

\Swoole\Runtime::enableCoroutine();
include 'plugins/autoloader.php';
\Swoole\Coroutine\run(function () {
    \Swoole\Coroutine::create(function () {
        $username = getenv('SIP_USERNAME') ?: '';
        $password = getenv('SIP_PASSWORD') ?: '';
        $domain = getenv('SIP_HOST') ?: 'spechshop.com';
        if (!filter_var($domain, FILTER_VALIDATE_IP)) {
            $host = gethostbyname($domain);
        } else {
            $host = $domain;
        }
        $phone = new trunkController($username, $password, $host);

        if (!$phone->register(10)) {
            throw new \Exception("Erro ao registrar");
        }


        $audioBuffer = '';
        $phone->onRinging(function ($phone) {
            cli::pcl("Chamada recebida", "yellow");
        });
        $phone->onHangup(function (trunkController $phone) use (&$audioBuffer) {
            $phone->saveBufferToWavFile('rec.wav', $audioBuffer);
            $phone->unblockCoroutine();
            cli::pcl("Bye recebido", "red");
        });
        $phone->mountLineCodecSDP('PCMU/8000');
        $phone->onReceivePcm(function ($pcmData, $peer, trunkController $phone) use (&$audioBuffer) {


            // optional
            $audioBuffer .= $pcmData;
        });
        $phone->onAnswer(function (trunkController $phone) {
            $phone->receiveMedia();
            //$phone->defineAudioFile('music.wav');
            cli::pcl("Chamada aceita", "green");
            \libspech\Sip\interruptibleSleep(10, $phone->receiveBye);

            $phone->send2833('*', 160);
            $phone->send2833(999999999, 160);
            \libspech\Sip\interruptibleSleep(5, $phone->receiveBye);
            $phone->bye();
            $phone->receiveBye = true;
            $phone->callActive = false;

        });
        $phone->onKeyPress(function ($event, $peer) use ($phone) {
            cli::pcl("Digitando: " . $event, "yellow");
        });
        $phone->call('5569984477329');
        $phone->saveBufferToWavFile('rec.wav', $audioBuffer);


        cli::pcl("Script finalizado", "green");
        $phone->close();
        cli::pcl("Processo cancelado", "red");
    });
});
cli::pcl("Processo encerrado com sucesso", "green");