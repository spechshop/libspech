<?php

/**
 * libspech - Example Usage
 *
 * Copyright (c) 2026 Lotus / berzersks
 * Website: https://spechshop.com
 * All Rights Reserved.
 *
 * PROPRIETARY SOFTWARE - Unauthorized use is prohibited.
 * Please respect the creator. See LICENSE for terms.
 */

// ============================================================================
// SESSÃO 1: CONFIGURAÇÕES INICIAIS
// ============================================================================
// Aumenta o limite de memória para 1GB - necessário para processar áudio
ini_set('memory_limit', '1024M');

// Importa as classes necessárias do sistema
use libspech\audio\EarlyGreetingDetector;
use libspech\Cli\cli;
use libspech\Sip\trunkController;
use function libspech\Sip\interruptibleSleep;

// Habilita o suporte a corotinas do Swoole para execução assíncrona
\Swoole\Runtime::enableCoroutine();


// Carrega o autoloader para importar todas as dependências do projeto
include 'plugins/autoloader.php';


// ============================================================================
// SESSÃO 2: INICIALIZAÇÃO DO AMBIENTE DE COROTINA
// ============================================================================
// Cria o ambiente de execução em corotina do Swoole
\Swoole\Coroutine\run(function () {
    // Cria uma nova corotina para executar o código SIP de forma assíncrona
    \Swoole\Coroutine::create(function () {

        // $s=microtime(true);
        // usleep(500_000);
        // $c = round(microtime(true)-$s,3);
        // cli::pcl("Corotina SIP iniciada em {$c} segundos", "bold_green");
        // exit;
        // ====================================================================
        // SESSÃO 3: CONFIGURAÇÃO DE CREDENCIAIS SIP
        // ====================================================================
        // Busca as credenciais SIP das variáveis de ambiente
        // Se não estiverem definidas, usa strings vazias como fallback
        $username = getenv('SIP_USERNAME') ?: '';
        $password = getenv('SIP_PASSWORD') ?: '';
        $domain = getenv('SIP_HOST') ?: 'spechshop.com';


        // Valida se o domínio é um IP ou hostname
        // Se for hostname, resolve para IP usando DNS


        //var_dump(\libspech\Network\network::resolveAddress($domain, 6));
        //exit;
        // Instancia o controlador do trunk SIP com as credenciais
        $phone = new trunkController($username, $password, $domain);
        $phone->setSipIpVersion(4);
        $phone->setPacketTime(20);
        $phone->mountLineCodecSDP('OPUS/48000/2');
        //$phone->mountLineCodecSDP('PCMU/8000/1');
        $phone->enableStereoSound();
        $phone->enableAudioRecording();
        $phone->enableAudioMemorySharing();
        $phone->defineAudioFile('music.wav');
        //$phone->enableVAD();
        //$phone->voiceActivityTimeout(3);


        //$phone->setCallerId('xxxxxxxxxxx');
        // ====================================================================
        // SESSÃO 4: REGISTRO SIP
        // ====================================================================
        // Tenta registrar no servidor SIP com timeout de 10 segundos
        // Se falhar, lança uma exceção e interrompe a execução
        if ($phone->register(5)) {
            cli::pcl("Registrado com sucesso", "green");
        } else {
            cli::pcl("Erro ao registrar", "red");
            return false;
        }

        // ====================================================================
        // SESSÃO 5: CONFIGURAÇÃO DE CALLBACKS DE EVENTOS
        // ====================================================================


        //$phone->mountLineCodecSDP('G729/8000');


        // Habilita a gravação de áudio durante a chamada


        // Callback executado quando uma chamada está tocando (ringing)

        $phone->onRinging(function () use (&$phone) {
            cli::pcl($phone->lastPacket['method'] . " Chamada TOCANDO " . microtime(true), "yellow");
            //\Swoole\Coroutine::sleep(5);
            //$phone->cancel();
        });
        $phone->onFailed(function ($message) use ( $phone) {
            cli::pcl("Chamada falhou: $message", "red");

        });
        $phone->onHangup(function (trunkController $phone) {
            // Salva o buffer de áudio gravado em um arquivo WAV
            $phone->saveBufferToWavFile('rec2.wav', $phone->getBuffer());
            // Desbloqueia a corotina para continuar a execução

            cli::pcl("Bye recebido", "red");

        });
        $phone->onSdpReceived(function (trunkController $phone) {
            cli::pcl("SDP recebido " .time(), "green");
            $phone->receiveMedia();
        });
        $phone->onAnswer(function (trunkController $phone)  {
            print $phone->getBuffer()->length();
            $phone->saveBufferToWavFile('ringbackgavado.wav', $phone->getBuffer());
            cli::pcl("Ringback foi gravado", "green");

            return $phone->bye();
        });

        $phone->onPacketOnTimeoutMedia(function ($peer) use ($phone) {
            cli::pcl("Timeout de mídia atingido, encerrando chamada", 'bold_red');
            $phone->bye();

            return true;
        });


        //$phone->enableStereoSound();


        //$phone->call('*53');
        $phone->call('5569984477329');


        $phone->saveBufferToWavFile('rec.wav', $phone->getBuffer());


        // ====================================================================
        // SESSÃO 9: FINALIZAÇÃO E LIMPEZA
        // ====================================================================
        cli::pcl("Script finalizado", "green");

        // Fecha a conexão SIP e libera recursos
        cli::pcl("Processo cancelado", "red");
        return 0;
    });
});

// Mensagem final indicando que o processo de corotina foi encerrado
cli::pcl("Processo encerrado com sucesso", "green");

