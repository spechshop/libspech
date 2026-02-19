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
use libspech\Cli\cli;
use libspech\Sip\trunkController;

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
        if (!filter_var($domain, FILTER_VALIDATE_IP)) {
            $host = gethostbyname($domain);
        } else {
            $host = $domain;
        }

        // Instancia o controlador do trunk SIP com as credenciais
        $phone = new trunkController($username, $password, $host);

        // ====================================================================
        // SESSÃO 4: REGISTRO SIP
        // ====================================================================
        // Tenta registrar no servidor SIP com timeout de 10 segundos
        // Se falhar, lança uma exceção e interrompe a execução
        if (!$phone->register(10)) {
            throw new \Exception("Erro ao registrar");
        }

        // ====================================================================
        // SESSÃO 5: CONFIGURAÇÃO DE CALLBACKS DE EVENTOS
        // ====================================================================

        // Callback executado quando uma chamada está tocando (ringing)
        $phone->onRinging(function ($phone) {
            cli::pcl("Chamada recebida", "yellow");
        });

        // Callback executado quando a chamada é desligada (hangup/bye)
        $phone->onHangup(function (trunkController $phone)  {
            // Salva o buffer de áudio gravado em um arquivo WAV
            $phone->saveBufferToWavFile('rec.wav', $phone->getBuffer());
            // Desbloqueia a corotina para continuar a execução
            $phone->unblockCoroutine();
            cli::pcl("Bye recebido", "red");

        });

        // ====================================================================
        // SESSÃO 6: CONFIGURAÇÃO DE CODEC E RECURSOS DE ÁUDIO
        // ====================================================================
        // Define o codec de áudio como OPUS 48kHz mono (1 canal)
        $phone->mountLineCodecSDP('OPUS/48000/1');

        // Habilita a gravação de áudio durante a chamada
        $phone->enableAudioRecording();

        // Habilita VAD (Voice Activity Detection) - detecta quando há voz ativa | Não recomendado pois gasta muitos recursos
        $phone->enableVAD();

        // Callback executado quando o VAD detecta mudança entre voz e silêncio
        $phone->onVadChange(function ($isVoiceActive, $energy, $id) {
            cli::pcl("VAD: $id " . ($isVoiceActive ? 'voice' : 'silence'). " Energy: $energy ".date('H:i:s'), ($isVoiceActive ? 'bold_green' : 'bold_red'));
        });
        // Callback executado quando a chamada é recebida/respondida
        $phone->onAnswer(function (trunkController $phone) {
            // Inicia o recebimento de mídia (áudio RTP)
            $phone->receiveMedia();

            cli::pcl("Chamada aceita", "green");

            // ================================================================
            // SESSÃO 7: FLUXO DE INTERAÇÃO NA CHAMADA
            // ================================================================

            // Aguarda 10 segundos de forma interruptível (pode ser cancelado se receber BYE)
            \libspech\Sip\interruptibleSleep(10, $phone->receiveBye);

            // Envia DTMF (tom de teclado) - caractere '*' com duração de 160ms
            $phone->send2833('*', 160);

            // Aguarda mais 10 segundos de forma interruptível
            \libspech\Sip\interruptibleSleep(10, $phone->receiveBye);

            // Envia DTMF com o valor 999999999 e duração de 960ms
            $phone->send2833(999999999);

            // Aguarda mais 10 segundos antes de encerrar
            \libspech\Sip\interruptibleSleep(30, $phone->receiveBye);

            // Envia BYE para encerrar a chamada
            $phone->bye();

            // Define flags indicando que a chamada foi encerrada
            $phone->receiveBye = true;
            $phone->callActive = false;
        });

        // Callback executado quando uma tecla DTMF é pressionada remotamente
        $phone->onKeyPress(function ($event, $peer) use ($phone) {
            cli::pcl("Digitando: " . $event, "yellow");
        });

        // ====================================================================
        // SESSÃO 8: INICIALIZAÇÃO DA CHAMADA
        // ====================================================================
        // Realiza uma chamada de saída para o número especificado
        $phone->call('551140040104');

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