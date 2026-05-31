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
    $totalCalls = 1;
    $durationSec = 120;
    $username = getenv('SIP_USERNAME') ?: '';
    $password = getenv('SIP_PASSWORD') ?: '';
    $domain = getenv('SIP_HOST') ?: 'spechshop.com';
    $host = filter_var($domain, FILTER_VALIDATE_IP) ? $domain : gethostbyname($domain);
    $destination = '551140040104';
    $calls = [];
    $stats = [];
    cli::pcl("Iniciando teste com {$totalCalls} chamadas por {$durationSec}s", "yellow");
    cli::pcl("Preço por minuto: R\$ " . number_format($priceMinute, 2, ',', '.'), "yellow");
    for ($i = 1; $i <= $totalCalls; $i++) {
        Coroutine::create(function () use ($i, $username, $password, $host, $destination, $durationSec, &$calls, &$stats) {
            $callKey = "call_{$i}";
            $phone = new trunkController($username, $password, $host);
            $phone->enableAudioMemorySharing();
            $phone->defineAudioFile('/home/lotus/projetos/libspech/music.wav');



            $stats[$callKey] = [
                'index' => $i,
                'answered' => false,
                'failed' => false,
                'started_at' => null,
                'ended_at' => null,
                'seconds' => 0,
                'error' => null
            ];
            $calls[$callKey] = $phone;
            $registered = $phone->register();
            if (!$registered) {
                $stats[$callKey]['failed'] = true;
                $stats[$callKey]['error'] = 'Erro ao registrar';
                cli::pcl("[{$callKey}] Erro ao registrar", "red");
                return;
            }
            cli::pcl("[{$callKey}] Registrado", "green");
            $phone->mountLineCodecSDP('PCMA/8000');
            $phone->onRinging(function () use ($phone, $callKey) {
                cli::pcl("[{$callKey}] Tocando", "yellow");
                if ($phone->audioRemoteIp) {
                    $phone->receiveMedia();
                }
            });
            $phone->onAnswer(function (trunkController $phone) use ($callKey, $durationSec, &$stats) {
                cli::pcl("[{$callKey}] Atendida", "green");
                $stats[$callKey]['answered'] = true;
                $stats[$callKey]['started_at'] = microtime(true);
                if ($phone->audioRemoteIp) {
                    $phone->receiveMedia();
                }
                Coroutine::sleep($durationSec);
                cli::pcl("[{$callKey}] Encerrando após {$durationSec}s", "yellow");
                $phone->bye();
                $stats[$callKey]['ended_at'] = microtime(true);
                $stats[$callKey]['seconds'] = $stats[$callKey]['ended_at'] - $stats[$callKey]['started_at'];
                $phone->receiveBye = true;
                $phone->callActive = false;
            });
            $phone->onFailed(function ($message) use ($callKey, &$stats, $phone) {
                $stats[$callKey]['failed'] = true;
                $stats[$callKey]['error'] = $message;
                cli::pcl("[{$callKey}] Falhou: {$message}", "red");
            });
            $phone->onHangup(function (trunkController $phone) use ($callKey, &$stats) {
                $phone->close();
                cli::pcl("[{$callKey}] BYE recebido", "red");
                if ($stats[$callKey]['started_at'] && !$stats[$callKey]['ended_at']) {
                    $stats[$callKey]['ended_at'] = microtime(true);
                    $stats[$callKey]['seconds'] = $stats[$callKey]['ended_at'] - $stats[$callKey]['started_at'];
                }
            });
            $phone->onPacketOnTimeoutMedia(function ($peer) use ($phone, $callKey, &$stats) {
                cli::pcl("[{$callKey}] Timeout de mídia", "bold_red");
                $stats[$callKey]['failed'] = true;
                $stats[$callKey]['error'] = 'Timeout de mídia';
                $phone->bye();
                $phone->close();
                return true;
            });
            cli::pcl("[{$callKey}] Ligando para {$destination}", "cyan");
            $phone->call($destination);
        });
        Coroutine::sleep(0.15);
    }
    $timerId = \Swoole\Timer::tick(5000, function () {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        cli::pcl("===== RESOURCE DEBUG =====", "bold_cyan");
        cli::pcl("RAM (Atual): " . round($memoryUsage / 1024 / 1024, 2) . " MB", "cyan");
        cli::pcl("RAM (Pico): " . round($memoryPeak / 1024 / 1024, 2) . " MB", "cyan");

        if (file_exists('/proc/self/stat')) {
            $stat = file_get_contents('/proc/self/stat');
            $parts = explode(' ', $stat);
            if (count($parts) >= 15) {
                $utime = intval($parts[13]);
                $stime = intval($parts[14]);
                $totalCpuTime = $utime + $stime;
                $clkTck = 100;
                $cpuSeconds = $totalCpuTime / $clkTck;
                cli::pcl("CPU (User time): " . round($utime / $clkTck, 2) . "s", "cyan");
                cli::pcl("Kernel (System time): " . round($stime / $clkTck, 2) . "s", "cyan");
                cli::pcl("CPU Total: " . round($cpuSeconds, 2) . "s", "cyan");
            }
        }
        cli::pcl("==========================", "bold_cyan");
    });


    Coroutine::sleep($durationSec );
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
    $expectedMinutes = $totalCalls * ($durationSec / 60);
    $expectedCharge = $expectedMinutes * $priceMinute;
    $finalMemoryUsage = memory_get_usage(true);
    $finalMemoryPeak = memory_get_peak_usage(true);
    $finalCpuUser = 0;
    $finalCpuSystem = 0;
    $finalCpuTotal = 0;

    if (file_exists('/proc/self/stat')) {
        $stat = file_get_contents('/proc/self/stat');
        $parts = explode(' ', $stat);
        if (count($parts) >= 15) {
            $utime = intval($parts[13]);
            $stime = intval($parts[14]);
            $clkTck = 100;
            $finalCpuUser = round($utime / $clkTck, 2);
            $finalCpuSystem = round($stime / $clkTck, 2);
            $finalCpuTotal = round(($utime + $stime) / $clkTck, 2);
        }
    }

    cli::pcl("======================================", "cyan");
    cli::pcl("Resumo final", "bold_green");
    cli::pcl("Chamadas planejadas: {$totalCalls}", "green");
    cli::pcl("Chamadas atendidas: {$answeredCalls}", "green");
    cli::pcl("Preço por minuto: R\$ " . number_format($priceMinute, 2, ',', '.'), "green");
    cli::pcl("Tempo esperado total: {$expectedMinutes} minuto(s)", "green");
    cli::pcl("Desconto esperado: R\$ " . number_format($expectedCharge, 2, ',', '.'), "bold_green");
    cli::pcl("Tempo real total: " . round($totalSeconds, 2) . " segundo(s)", "yellow");
    cli::pcl("Desconto por tempo real: R\$ " . number_format($totalToChargeReal, 2, ',', '.'), "yellow");
    cli::pcl("--- Recursos Consumidos ---", "bold_yellow");
    cli::pcl("Memoria final: " . round($finalMemoryUsage / 1024 / 1024, 2) . " MB", "yellow");
    cli::pcl("Memoria pico: " . round($finalMemoryPeak / 1024 / 1024, 2) . " MB", "yellow");
    cli::pcl("CPU user: {$finalCpuUser}s | system: {$finalCpuSystem}s | total: {$finalCpuTotal}s", "yellow");
    cli::pcl("======================================", "cyan");
    foreach ($stats as $callKey => $data) {
        $seconds = round($data['seconds'], 2);
        $minutes = $data['seconds'] / 60;
        $charge = $minutes * $priceMinute;
        cli::pcl("[{$callKey}] answered=" . ($data['answered'] ? 'sim' : 'nao') . " failed=" . ($data['failed'] ? 'sim' : 'nao') . " seconds={$seconds}" . " charge=R\$ " . number_format($charge, 4, ',', '.') . (!empty($data['error']) ? " error={$data['error']}" : ""), "white");
    }
    foreach ($calls as $phone) {
        try {
            $phone->close();
            $phone->unRegister();
        } catch (Throwable $e) {
        }
    }
    \Swoole\Timer::clear($timerId);
    cli::pcl("Teste finalizado", "green");
});