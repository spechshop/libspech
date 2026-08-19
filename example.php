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
        $domain = getenv('SIP_HOST') ?: 'example.com';


        // Valida se o domínio é um IP ou hostname
        // Se for hostname, resolve para IP usando DNS


        // Instancia o controlador do trunk SIP com as credenciais
        $phone = new trunkController($username, $password, $domain);
        $phone->setSipIpVersion(4);

        //$phone->enableVAD();
        //$phone->voiceActivityTimeout(3);


        //$phone->setCallerId('xxxxxxxxxxx');
        // ====================================================================
        // SESSÃO 4: REGISTRO SIP
        // ====================================================================
        // Tenta registrar no servidor SIP com timeout de 10 segundos
        // Se falhar, lança uma exceção e interrompe a execução
        cli::pcl("Registering <sip:$username@$domain>;$password");
        if (!$phone->register(5)) {
            cli::pcl("Erro ao registrar", "red");

            return false;
        } else {
            cli::pcl("Registrado com sucesso", "green");

        }


        // ====================================================================
        // SESSÃO 5: CONFIGURAÇÃO DE CALLBACKS DE EVENTOS
        // ====================================================================


        $phone->mountLineCodecSDP('PCMU/8000');
        //$phone->mountLineCodecSDP('OPUS/48000/2');
        $phone->enableAudioRecording();
        $phone->enableAudioMemorySharing();
        $phone->defineAudioFile('silence_5m.wav');

        // Habilita a gravação de áudio durante a chamada


        // Callback executado quando uma chamada está tocando (ringing)

        $detector = new EarlyGreetingDetector(
            sampleRate: 8000,
            frameDurationMs: 20,
            analysisWindowMs: 200,
            minimumGreetingVoiceMs: 800,
            maximumInternalGapMs: 120,
            minimumVoiceDbfs: -42.0,
            noiseMarginDb: 10.0,
        );




        $detector->onGreetingDetected(
            function (array $event): void {
                printf(
                    "[%8.3f s] SAUDACAO EM EARLY MEDIA | voz=%d ms\n",
                    $event['audio_ms'] / 1000,
                    $event['voiced_ms']
                );
            }
        );

        $detector->onVoiceEnd(
            function (array $event): void {
                if ( $event['greeting_detected'])
                printf(
                    "[%8.3f s] Voz finalizada | voz=%d ms | saudacao=%s\n",
                    $event['audio_ms'] / 1000,
                    $event['voiced_ms'],
                    $event['greeting_detected'] ? 'SIM' : 'NAO'
                );
            }
        );




        $phone->onReceivePcm(function (string $pcmData, array $peer, trunkController $phone) use ($detector): void {
            $detector->push($pcmData);
        });
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
            $phone->saveBufferToWavFile('rec.wav', $phone->getBuffer());
            // Desbloqueia a corotina para continuar a execução

            cli::pcl("Bye recebido", "red");

        });
        $phone->onSdpReceived(function (trunkController $phone) {
            cli::pcl("SDP recebido " . microtime(true), "green");
            $phone->receiveMedia();
        });
        $phone->onAnswer(function (trunkController $phone) use ($detector) {
            $detector->markAnswered();



            cli::pcl("Chamada recebida", "green");

            cli::pcl("IP remoto: " . $phone->audioRemoteIp . ':' . $phone->audioRemotePort, "yellow");
            // Inicia o recebimento de mídia (áudio RTP)


            // ================================================================
            // SESSÃO 7: FLUXO DE INTERAÇÃO NA CHAMADA
            // ================================================================

            // Aguarda 10 segundos de forma interruptível (pode ser cancelado se receber BYE)


            // Envia DTMF (tom de teclado) - caractere '*' com duração de 160ms


            $phone->waitSilence(false, 10);


            $buffer = $phone->getBuffer();
            $bufferLen = $buffer->length();


            $phone->send2833('#');


            $cpf = '42017165204';
            interruptibleSleep(3, $phone->receiveBye);
            foreach (str_split(substr($cpf, 0, 11)) as $digit) {
                $phone->send2833($digit);
                cli::pcl("Digitando: " . $digit, "yellow");
            }
            cli::pcl("Digitado: " . $cpf, "green");
            $phone->waitSilence(false, 10);

            interruptibleSleep(3, $phone->receiveBye);


            $phone->bye();
            $phone->close();

            // Define flags indicando que a chamada foi encerrada
            $phone->receiveBye = true;
            $phone->callActive = false;
        });
        $phone->onKeyPress(function ($event, $peer) use ($phone) {
            cli::pcl("$phone->calledNumber Digitou: " . $event, "yellow");
        });
        $phone->onPacketOnTimeoutMedia(function ($peer) use ($phone) {
            cli::pcl("Timeout de mídia atingido, encerrando chamada", 'bold_red');
            $phone->bye();
            $phone->close();
            return true;
        });


        //$phone->enableStereoSound();


        $phone->call('553140040104');


        $phone->saveBufferToWavFile('rec.wav', $phone->getBuffer());


        // ====================================================================
        // SESSÃO 9: FINALIZAÇÃO E LIMPEZA
        // ====================================================================
        cli::pcl("Script finalizado", "green");

        // Fecha a conexão SIP e libera recursos
        $phone->close();

        cli::pcl("Processo cancelado", "red");
    });
});

// Mensagem final indicando que o processo de corotina foi encerrado
cli::pcl("Processo encerrado com sucesso", "green");

