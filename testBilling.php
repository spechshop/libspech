<?php

ini_set('memory_limit', '1024M');

use libspech\Cli\cli;
use libspech\Sip\trunkController;
use Swoole\Coroutine;
use Swoole\Runtime;
use function Swoole\Coroutine\run;

Runtime::enableCoroutine();

include 'plugins/autoloader.php';

run(function () {
    $priceMinute = '0.15';
    $totalCalls = 20;
    $durationSec = 30;

    $username = getenv('SIP_USERNAME') ?: '';
    $password = getenv('SIP_PASSWORD') ?: '';
    $domain = getenv('SIP_HOST') ?: 'spechshop.com';

    $host = filter_var($domain, FILTER_VALIDATE_IP)
        ? $domain
        : gethostbyname($domain);

    $destination = '553140040104';

    $calls = [];
    $stats = [];

    cli::pcl(
        "Iniciando teste com {$totalCalls} chamadas por {$durationSec}s",
        "yellow"
    );

    cli::pcl(
        "Preço por minuto: R\$ " . number_format($priceMinute, 2, ',', '.'),
        "yellow"
    );

    for ($i = 1; $i <= $totalCalls; $i++) {
        Coroutine::create(function () use (
            $i,
            $totalCalls,
            $username,
            $password,
            $host,
            $destination,
            $durationSec,
            &$calls,
            &$stats
        ) {
            $callKey = "call_{$i}";

            $phone = new trunkController(
                $username,
                $password,
                $host
            );
            $phone->setCallerId('556921815666');

            $phone->mountLineCodecSDP('PCMA/8000');
            $phone->enableAudioMemorySharing();
            $phone->enableAudioRecording();
            $phone->setPacketTime(20);

            $phone->defineAudioFile('ss.wav');

            $stats[$callKey] = [
                'index' => $i,
                'answered' => false,
                'failed' => false,
                'finished' => false,
                'started_at' => null,
                'ended_at' => null,
                'seconds' => 0,
                'bytes' => 0,
                'error' => null
            ];

            $calls[$callKey] = $phone;

            $registered = $phone->register();

            if (!$registered) {
                $stats[$callKey]['failed'] = true;
                $stats[$callKey]['error'] = 'Erro ao registrar';
                $stats[$callKey]['finished'] = true;

                cli::pcl(
                    "[{$callKey}] Erro ao registrar",
                    "red"
                );

                return;
            }

            cli::pcl(
                "[{$callKey}] Registrado",
                "green"
            );

            $phone->onRinging(
                function () use ($phone, $callKey) {
                    cli::pcl(
                        "[{$callKey}] Tocando",
                        "yellow"
                    );
                }
            );

            $phone->onSdpReceived(
                function (trunkController $phone) use ($callKey) {
                    cli::pcl(
                        "[{$callKey}] Recebendo SDP",
                        "blue"
                    );

                    $phone->receiveMedia();
                }
            );

            $phone->onAnswer(
                function (trunkController $phone) use (
                    $callKey,
                    &$durationSec,
                    &$stats
                ) {
                    cli::pcl(
                        "[{$callKey}] Atendida",
                        "green"
                    );

                    $stats[$callKey]['answered'] = true;
                    $stats[$callKey]['started_at'] = microtime(true);

                    if ($phone->audioRemoteIp) {
                        $phone->receiveMedia();
                    }

                    Coroutine::sleep($durationSec);

                    cli::pcl(
                        "[{$callKey}] Encerrando após {$durationSec}s",
                        "yellow"
                    );

                    $phone->bye();

                    $stats[$callKey]['ended_at'] = microtime(true);

                    $stats[$callKey]['seconds'] =
                        $stats[$callKey]['ended_at']
                        - $stats[$callKey]['started_at'];

                    $stats[$callKey]['finished'] = true;

                    $stats[$callKey]['bytes'] =
                        $phone->getBuffer()->length();

                    $phone->close();
                }
            );

            $phone->onFailed(
                function ($message) use (
                    $callKey,
                    &$stats,
                    $phone
                ) {
                    $stats[$callKey]['failed'] = true;
                    $stats[$callKey]['error'] = $message;
                    $stats[$callKey]['finished'] = true;

                    cli::pcl(
                        "[{$callKey}] Falhou: {$message}",
                        "red"
                    );
                }
            );

            $phone->onHangup(
                function (trunkController $phone) use (
                    $callKey,
                    &$stats
                ) {
                    $phone->close();

                    cli::pcl(
                        "[{$callKey}] BYE recebido",
                        "red"
                    );

                    if (
                        $stats[$callKey]['started_at']
                        && !$stats[$callKey]['ended_at']
                    ) {
                        $stats[$callKey]['ended_at'] = microtime(true);

                        $stats[$callKey]['seconds'] =
                            $stats[$callKey]['ended_at']
                            - $stats[$callKey]['started_at'];
                    }

                    $stats[$callKey]['finished'] = true;
                }
            );

            $phone->onPacketOnTimeoutMedia(
                function ($peer) use (
                    &$phone,
                    $callKey,
                    &$stats
                ) {
                    cli::pcl(
                        "[{$callKey}] Timeout de mídia",
                        "bold_red"
                    );

                    $stats[$callKey]['failed'] = true;
                    $stats[$callKey]['error'] = 'Timeout de mídia';
                    $stats[$callKey]['finished'] = true;

                    $phone->bye();
                    $phone->close();

                    return true;
                }
            );

            if ($i === $totalCalls) {
                // chamo um numero para eu mesmo atender e saber se audio esta normal
                $destination = '556921815878';
            }

            cli::pcl(
                "[{$callKey}] Ligando para {$destination}",
                "cyan"
            );

            $phone->call($destination);
        });

        Coroutine::sleep(0.15);
    }

    $timerId = \Swoole\Timer::tick(
        5000,
        function () use (
            &$stats,
            $totalCalls
        ) {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);

            cli::pcl(
                "===== RESOURCE DEBUG =====",
                "bold_cyan"
            );

            cli::pcl(
                "RAM (Atual): "
                . round($memoryUsage / 1024 / 1024, 2)
                . " MB",
                "cyan"
            );

            cli::pcl(
                "RAM (Pico): "
                . round($memoryPeak / 1024 / 1024, 2)
                . " MB",
                "cyan"
            );

            $coroStats = Coroutine::stats();

            cli::pcl(
                "Corrotinas: "
                . ($coroStats['coroutine_num'] ?? 0),
                "cyan"
            );

            $finishedCount = 0;

            foreach ($stats as $data) {
                if (!empty($data['finished'])) {
                    $finishedCount++;
                }
            }

            cli::pcl(
                "Chamadas: {$finishedCount}/{$totalCalls} finalizadas",
                "cyan"
            );

            cli::pcl(
                "==========================",
                "bold_cyan"
            );
        }
    );

    cli::pcl(
        "Aguardando finalização de todas as chamadas...",
        "yellow"
    );

    $startWait = time();
    $timeout = $durationSec + 60;

    while (true) {
        $finishedCount = 0;

        foreach ($stats as $data) {
            if (!empty($data['finished'])) {
                $finishedCount++;
            }
        }

        if (
            $finishedCount >= $totalCalls
            || (time() - $startWait) > $timeout
        ) {
            break;
        }

        Coroutine::sleep(0.5);
    }

    $answeredCalls = 0;
    $totalSeconds = 0;

    foreach ($stats as $callKey => $data) {
        if (!empty($data['answered'])) {
            $answeredCalls++;
            $totalSeconds += $data['seconds'];
        }
    }

    $totalMinutesReal = $totalSeconds / 60;
    $totalToChargeReal = $totalMinutesReal * $priceMinute;

    $expectedMinutes =
        $totalCalls * ($durationSec / 60);

    $expectedCharge =
        $expectedMinutes * $priceMinute;

    $finalMemoryUsage =
        memory_get_usage(true);

    $finalMemoryPeak =
        memory_get_peak_usage(true);

    cli::pcl(
        "======================================",
        "cyan"
    );

    cli::pcl(
        "Resumo final",
        "bold_green"
    );

    cli::pcl(
        "Chamadas planejadas: {$totalCalls}",
        "green"
    );

    cli::pcl(
        "Chamadas atendidas: {$answeredCalls}",
        "green"
    );

    cli::pcl(
        "Preço por minuto: R\$ "
        . number_format(
            $priceMinute,
            2,
            ',',
            '.'
        ),
        "green"
    );

    cli::pcl(
        "Tempo esperado total: {$expectedMinutes} minuto(s)",
        "green"
    );

    cli::pcl(
        "Desconto esperado: R\$ "
        . number_format(
            $expectedCharge,
            2,
            ',',
            '.'
        ),
        "bold_green"
    );

    cli::pcl(
        "Tempo real total: "
        . round($totalSeconds, 2)
        . " segundo(s)",
        "yellow"
    );

    cli::pcl(
        "Desconto por tempo real: R\$ "
        . number_format(
            $totalToChargeReal,
            2,
            ',',
            '.'
        ),
        "yellow"
    );

    cli::pcl(
        "--- Recursos Consumidos ---",
        "bold_yellow"
    );

    cli::pcl(
        "Memoria final: "
        . round(
            $finalMemoryUsage / 1024 / 1024,
            2
        )
        . " MB",
        "yellow"
    );

    cli::pcl(
        "Memoria pico: "
        . round(
            $finalMemoryPeak / 1024 / 1024,
            2
        )
        . " MB",
        "yellow"
    );

    cli::pcl(
        "======================================",
        "cyan"
    );

    foreach ($stats as $callKey => $data) {
        $seconds =
            round($data['seconds'], 2);

        $minutes =
            $data['seconds'] / 60;

        $charge =
            $minutes * $priceMinute;

        cli::pcl(
            "[{$callKey}] answered="
            . ($data['answered'] ? 'sim' : 'nao')
            . " failed="
            . ($data['failed'] ? 'sim' : 'nao')
            . " bytes={$data['bytes']}"
            . " seconds={$seconds}"
            . " charge=R\$ "
            . number_format(
                $charge,
                4,
                ',',
                '.'
            )
            . (
            !empty($data['error'])
                ? " error={$data['error']}"
                : ""
            ),
            "white"
        );
    }

    foreach ($calls as $phone) {
        try {
            $phone->unRegister();
            $phone->close();
        } catch (Throwable $e) {
        }
    }

    \Swoole\Timer::clear($timerId);

    cli::pcl(
        "Teste finalizado",
        "green"
    );
});