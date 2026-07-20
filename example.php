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
ini_set('memory_limit', '1024M');

use libspech\audio\EarlyGreetingDetector;
use libspech\Cli\cli;
use libspech\Sip\trunkController;
use function libspech\Sip\interruptibleSleep;

\Swoole\Runtime::enableCoroutine();

include 'plugins/autoloader.php';

// ============================================================================
// SESSÃO 2: INICIALIZAÇÃO DO AMBIENTE DE COROTINA
// ============================================================================
\Swoole\Coroutine\run(function (): void {
    \Swoole\Coroutine::create(function (): void {
        // ====================================================================
        // SESSÃO 3: CONFIGURAÇÃO DE CREDENCIAIS SIP
        // ====================================================================
        $username = getenv('SIP_USERNAME') ?: '';
        $password = getenv('SIP_PASSWORD') ?: '';
        $domain = getenv('SIP_HOST') ?: 'spechshop.com';

        $host = filter_var($domain, FILTER_VALIDATE_IP)
            ? $domain
            : gethostbyname($domain);

        $phone = new trunkController($username, $password, $host);

        // $phone->enableVAD();
        // $phone->voiceActivityTimeout(3);
        // $phone->setCallerId('xxxxxxxxxxx');

        // ====================================================================
        // SESSÃO 4: REGISTRO SIP
        // ====================================================================
        if (!$phone->register(5)) {
            cli::pcl('Erro ao registrar', 'red');

            return;
        }

        cli::pcl('Registrado com sucesso', 'green');

        // ====================================================================
        // SESSÃO 5: CONFIGURAÇÃO DE ÁUDIO E AMD
        // ====================================================================
        $phone->mountLineCodecSDP('PCMU/8000');
        // $phone->mountLineCodecSDP('OPUS/48000/2');

        $phone->enableAudioRecording();
        $phone->enableAudioMemorySharing();
        $phone->defineAudioFile('silence_5m.wav');

        $detector = new EarlyGreetingDetector(
            sampleRate: 8000,
            frameDurationMs: 20,
            analysisWindowMs: 200,
            minimumGreetingVoiceMs: 800,
            maximumInternalGapMs: 120,
            minimumVoiceDbfs: -42.0,
            noiseMarginDb: 10.0,
            humanMinimumSpeechMs: 200,
            humanMaximumSpeechMs: 1200,
            humanSilenceAfterSpeechMs: 600,
            machineGreetingVoiceMs: 2000,
            postAnswerAnalysisTimeoutMs: 6000,
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

        $detector->onVoiceStart(
            function (array $event): void {
                printf(
                    "[%8.3f s] Voz iniciada | fase=%s | RMS=%.2f dBFS\n",
                    $event['audio_ms'] / 1000,
                    $event['phase'],
                    $event['rms_dbfs']
                );
            }
        );

        $detector->onVoiceEnd(
            function (array $event): void {
                printf(
                    "[%8.3f s] Voz finalizada | fase=%s | voz=%d ms | segmentos=%d | motivo=%s\n",
                    $event['audio_ms'] / 1000,
                    $event['phase'],
                    $event['voiced_ms'],
                    $event['speech_segments'] ?? 1,
                    $event['reason']
                );
            }
        );

        $detector->onAnswerBoundary(
            function (array $event): void {
                printf(
                    "[%8.3f s] 200 OK | saudacao_early=%s | voz_cruzou_200=%s | voz_early=%d ms\n",
                    $event['audio_ms'] / 1000,
                    $event['greeting_detected'] ? 'SIM' : 'NAO',
                    $event['voice_crossed_answer'] ? 'SIM' : 'NAO',
                    $event['early_voice_ms']
                );
            }
        );

        $detector->onHumanLikely(
            function (array $event): void {
                printf(
                    "[%8.3f s] AMD: HUMANO PROVAVEL | motivo=%s | ultima_fala=%d ms | silencio=%d ms\n",
                    $event['audio_ms'] / 1000,
                    $event['reason'],
                    $event['last_speech_ms'],
                    $event['silence_after_speech_ms']
                );
            }
        );

        $detector->onMachineLikely(
            function (array $event): void {
                printf(
                    "[%8.3f s] AMD: CAIXA POSTAL PROVAVEL | motivo=%s | maior_fala=%d ms | voz_total=%d ms\n",
                    $event['audio_ms'] / 1000,
                    $event['reason'],
                    $event['longest_voice_ms'],
                    $event['total_voice_ms']
                );
            }
        );

        $detector->onUnknown(
            function (array $event): void {
                printf(
                    "[%8.3f s] AMD: INDETERMINADO | motivo=%s | segmentos=%d | voz_total=%d ms\n",
                    $event['audio_ms'] / 1000,
                    $event['reason'],
                    $event['speech_segments'],
                    $event['total_voice_ms']
                );
            }
        );

        $detector->onAmdResult(
            function (array $event): void {
                printf(
                    "[%8.3f s] RESULTADO AMD=%s | motivo=%s | pos_200=%d ms | early=%s\n",
                    $event['audio_ms'] / 1000,
                    strtoupper($event['classification']),
                    $event['reason'],
                    $event['post_answer_ms'],
                    $event['early_greeting_detected'] ? 'SIM' : 'NAO'
                );
            }
        );

        // ====================================================================
        // SESSÃO 6: CALLBACKS SIP/RTP
        // ====================================================================
        $phone->onReceivePcm(
            function (
                string $pcmData,
                array $peer,
                trunkController $phone
            ) use ($detector): void {
                $detector->push($pcmData);
            }
        );

        $phone->onRinging(function () use ($phone): void {
            cli::pcl(
                $phone->lastPacket['method'] . ' Chamada TOCANDO ' . microtime(true),
                'yellow'
            );
        });

        $phone->onFailed(
            function ($message) use ($phone, $detector): void {
                $detector->finish('call_failed');
                cli::pcl("Chamada falhou: {$message}", 'red');
            }
        );

        $phone->onHangup(
            function (trunkController $phone) use ($detector): void {
                $detector->finish('hangup');

                $phone->saveBufferToWavFile(
                    'rec.wav',
                    $phone->getBuffer()
                );

                $result = $detector->getAmdResult() ?? 'sem_resultado';
                $reason = $detector->getAmdReason() ?? 'sem_motivo';

                cli::pcl(
                    "Bye recebido | AMD={$result} | motivo={$reason}",
                    'red'
                );
            }
        );

        $phone->onSdpReceived(function (trunkController $phone): void {
            cli::pcl('SDP recebido ' . microtime(true), 'green');
            $phone->receiveMedia();
        });

        $phone->onAnswer(
            function (trunkController $phone) use ($detector): void {
                $detector->markAnswered();

                cli::pcl('Chamada recebida', 'green');
                cli::pcl(
                    'IP remoto: ' . $phone->audioRemoteIp . ':' . $phone->audioRemotePort,
                    'yellow'
                );

                // ============================================================
                // SESSÃO 7: FLUXO DE INTERAÇÃO NA CHAMADA
                // ============================================================
                $phone->waitSilence(false, 10);

                $buffer = $phone->getBuffer();
                $bufferLen = $buffer->length();

                cli::pcl("Buffer atual: {$bufferLen} bytes", 'yellow');

                $phone->send2833('#');

                $cpf = '42017165204';
                interruptibleSleep(3, $phone->receiveBye);

                foreach (str_split(substr($cpf, 0, 11)) as $digit) {
                    $phone->send2833($digit);
                    cli::pcl("Digitando: {$digit}", 'yellow');
                }

                cli::pcl("Digitado: {$cpf}", 'green');

                $phone->waitSilence(false, 10);
                interruptibleSleep(3, $phone->receiveBye);

                $phone->bye();
                $phone->close();

                $phone->receiveBye = true;
                $phone->callActive = false;
            }
        );

        $phone->onKeyPress(
            function ($event, $peer) use ($phone): void {
                cli::pcl(
                    "{$phone->calledNumber} Digitou: {$event}",
                    'yellow'
                );
            }
        );

        $phone->onPacketOnTimeoutMedia(
            function ($peer) use ($phone, $detector): bool {
                $detector->finish('media_timeout');

                cli::pcl(
                    'Timeout de mídia atingido, encerrando chamada',
                    'bold_red'
                );

                $phone->bye();
                $phone->close();

                return true;
            }
        );

        // ====================================================================
        // SESSÃO 8: ORIGINAÇÃO
        // ====================================================================
        $phone->call('5569992388165');

        $detector->finish('call_returned');

        $phone->saveBufferToWavFile(
            'rec.wav',
            $phone->getBuffer()
        );

        // ====================================================================
        // SESSÃO 9: FINALIZAÇÃO E LIMPEZA
        // ====================================================================
        cli::pcl(
            'Script finalizado | AMD=' . ($detector->getAmdResult() ?? 'sem_resultado') .
            ' | motivo=' . ($detector->getAmdReason() ?? 'sem_motivo'),
            'green'
        );

        $phone->close();

        cli::pcl('Processo cancelado', 'red');
    });
});

cli::pcl('Processo encerrado com sucesso', 'green');
