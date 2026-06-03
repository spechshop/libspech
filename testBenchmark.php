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
    $totalCalls = 50; // Mais chamadas para benchmark de desempenho
    $durationSec = 10; // Duração menor para foco em throughput
    $username = getenv('SIP_USERNAME') ?: '';
    $password = getenv('SIP_PASSWORD') ?: '';
    $domain = getenv('SIP_HOST') ?: 'spechshop.com';
    $host = filter_var($domain, FILTER_VALIDATE_IP) ? $domain : gethostbyname($domain);
    $destination = '553140040104';
    $calls = [];
    $stats = [];
    $startBenchmark = microtime(true);
    cli::pcl("Iniciando BENCHMARK de desempenho com {$totalCalls} chamadas por {$durationSec}s", "yellow");
    cli::pcl("Preço por minuto: R\$ " . number_format($priceMinute, 2, ',', '.'), "yellow");
    cli::pcl("Foco: latência, throughput, recursos", "bold_cyan");
    for ($i = 1; $i <= $totalCalls; $i++) {
        Coroutine::create(function () use ($i, $totalCalls, $username, $password, $host, $destination, $durationSec, &$calls, &$stats) {
            $callKey = "call_{$i}";
            $phone = new trunkController($username, $password, $host);
            $phone->mountLineCodecSDP('PCMA/8000');
            $phone->enableAudioMemorySharing();
            $phone->enableAudioRecording();

            $phone->defineAudioFile('ss.wav');



            $stats[$callKey] = [
                'index' => $i,
                'answered' => false,
                'failed' => false,
                'finished' => false,
                'started_at' => null,
                'ended_at' => null,
                'setup_time' => 0,
                'seconds' => 0,
                'bytes' => 0,
                'error' => null
            ];
            $calls[$callKey] = $phone;
            $registerStart = microtime(true);
            $registered = $phone->register(2);
            if (!$registered) {
                $stats[$callKey]['failed'] = true;
                $stats[$callKey]['error'] = 'Erro ao registrar';
                $stats[$callKey]['finished'] = true;
                cli::pcl("[{$callKey}] Erro ao registrar", "red");
                return;
            }
            $stats[$callKey]['setup_time'] = microtime(true) - $registerStart;
            cli::pcl("[{$callKey}] Registrado (setup: " . round($stats[$callKey]['setup_time'], 3) . "s)", "green");

            $phone->onRinging(function () use ($phone, $callKey) {
                cli::pcl("[{$callKey}] Tocando", "yellow");
            });
            $phone->onSdpReceived(function (trunkController $phone) use ($callKey) {
                cli::pcl("[{$callKey}] Recebendo SDP", "blue");
                $phone->receiveMedia();
            });




            $phone->onAnswer(function (trunkController $phone) use ($callKey, &$durationSec, &$stats) {
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
                $stats[$callKey]['finished'] = true;
                $stats[$callKey]['bytes'] = $phone->getBuffer()->length();
                $phone->close();

            });
            $phone->onFailed(function ($message) use ($callKey, &$stats, $phone) {
                $stats[$callKey]['failed'] = true;
                $stats[$callKey]['error'] = $message;
                $stats[$callKey]['finished'] = true;
                cli::pcl("[{$callKey}] Falhou: {$message}", "red");
            });
            $phone->onHangup(function (trunkController $phone) use ($callKey, &$stats) {
                $phone->close();
                cli::pcl("[{$callKey}] BYE recebido", "red");
                if ($stats[$callKey]['started_at'] && !$stats[$callKey]['ended_at']) {
                    $stats[$callKey]['ended_at'] = microtime(true);
                    $stats[$callKey]['seconds'] = $stats[$callKey]['ended_at'] - $stats[$callKey]['started_at'];
                }
                $stats[$callKey]['finished'] = true;
            });
            $phone->onPacketOnTimeoutMedia(function ($peer) use (&$phone, $callKey, &$stats) {
                cli::pcl("[{$callKey}] Timeout de mídia", "bold_red");
                $stats[$callKey]['failed'] = true;
                $stats[$callKey]['error'] = 'Timeout de mídia';
                $stats[$callKey]['finished'] = true;
                $phone->bye();
                $phone->close();
                return true;
            });
            if ($i === $totalCalls) {
                //$destination = '5569984477329';
            }
            cli::pcl("[{$callKey}] Ligando para {$destination}", "cyan");
            $phone->call($destination);
        });
        Coroutine::sleep(0.1); // Intervalo menor para maior taxa de chamadas
    }
    $lastCpuTime = 0;
    if (file_exists('/proc/self/stat')) {
        $stat = file_get_contents('/proc/self/stat');
        $parts = explode(' ', $stat);
        if (count($parts) >= 15) {
            $lastCpuTime = intval($parts[13]) + intval($parts[14]);
        }
    }
    $lastWallTime = microtime(true);

    $timerId = \Swoole\Timer::tick(5000, function () use (&$lastCpuTime, &$lastWallTime, &$stats, $totalCalls) {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $now = microtime(true);

        cli::pcl("===== RESOURCE DEBUG (BENCHMARK) =====", "bold_cyan");
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

                // Cálculo de CPU %
                $deltaCpu = $totalCpuTime - $lastCpuTime;
                $deltaTime = $now - $lastWallTime;
                $cpuUsagePct = 0;
                if ($deltaTime > 0) {
                    $cpuUsagePct = ($deltaCpu / $clkTck) / $deltaTime * 100;
                }

                cli::pcl("CPU Usage: " . round($cpuUsagePct, 2) . "%", "bold_yellow");
                cli::pcl("CPU Total (User): " . round($utime / $clkTck, 2) . "s | (Kernel): " . round($stime / $clkTck, 2) . "s", "cyan");

                $lastCpuTime = $totalCpuTime;
                $lastWallTime = $now;
            }
        }

        $coroStats = Coroutine::stats();
        cli::pcl("Corrotinas: " . ($coroStats['coroutine_num'] ?? 0), "cyan");

        $finishedCount = 0;
        foreach ($stats as $data) {
            if (!empty($data['finished'])) {
                $finishedCount++;
            }
        }
        cli::pcl("Chamadas: {$finishedCount}/{$totalCalls} finalizadas", "cyan");

        cli::pcl("==========================", "bold_cyan");
    });


    cli::pcl("Aguardando finalização de todas as chamadas...", "yellow");
    $startWait = time();
    $timeout = $durationSec + 90;
    while (true) {
        $finishedCount = 0;
        foreach ($stats as $data) {
            if (!empty($data['finished'])) {
                $finishedCount++;
            }
        }
        if ($finishedCount >= $totalCalls || (time() - $startWait) > $timeout) {
            break;
        }
        Coroutine::sleep(0.5);
    }
    $answeredCalls = 0;
    $totalSeconds = 0;
    $totalSetupTime = 0;
    $setupTimes = [];
    foreach ($stats as $callKey => $data) {
        if (!empty($data['answered'])) {
            $answeredCalls++;
            $totalSeconds += $data['seconds'];
        }
        if (!empty($data['setup_time'])) {
            $totalSetupTime += $data['setup_time'];
            $setupTimes[] = $data['setup_time'];
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

    $endBenchmark = microtime(true);
    $totalBenchmarkTime = $endBenchmark - $startBenchmark;
    $callsPerSecond = $totalCalls / $totalBenchmarkTime;
    $avgSetupTime = count($setupTimes) > 0 ? array_sum($setupTimes) / count($setupTimes) : 0;
    $maxSetupTime = count($setupTimes) > 0 ? max($setupTimes) : 0;
    $minSetupTime = count($setupTimes) > 0 ? min($setupTimes) : 0;

    $successRate = $totalCalls > 0 ? ($answeredCalls / $totalCalls) : 0;
    $setupScore = ($avgSetupTime > 0 && $avgSetupTime < 0.3) ? 10 : (($avgSetupTime < 0.8) ? 8 : (($avgSetupTime < 1.5) ? 6 : 4));
    $throughputScore = min(10, max(0, $callsPerSecond * 4));
    $failPenalty = 0;
    foreach ($stats as $data) { if (!empty($data['failed'])) $failPenalty += 1; }
    $failScore = max(0, 10 - $failPenalty * 2);
    $nota = round(($successRate * 10 * 0.35 + $setupScore * 0.25 + $throughputScore * 0.25 + $failScore * 0.15));
    $nota = max(0, min(10, $nota));

    cli::pcl("======================================", "cyan");
    cli::pcl("RESUMO DE BENCHMARK DE DESEMPENHO", "bold_green");
    cli::pcl("Nota de avaliação: {$nota}/10", "bold_green");
    cli::pcl("Chamadas planejadas: {$totalCalls}", "green");
    cli::pcl("Chamadas atendidas: {$answeredCalls}", "green");
    cli::pcl("Tempo total do benchmark: " . round($totalBenchmarkTime, 2) . "s", "bold_yellow");
    cli::pcl("Throughput: " . round($callsPerSecond, 2) . " chamadas/s", "bold_yellow");
    cli::pcl("Setup time médio: " . round($avgSetupTime, 3) . "s (min: " . round($minSetupTime, 3) . "s, max: " . round($maxSetupTime, 3) . "s)", "cyan");
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
        $setup = round($data['setup_time'] ?? 0, 3);
        cli::pcl("[{$callKey}] answered=" . ($data['answered'] ? 'sim' : 'nao') . " failed=" . ($data['failed'] ? 'sim' : 'nao') . " bytes=$data[bytes] seconds={$seconds} setup={$setup}s" . " charge=R\$ " . number_format($charge, 4, ',', '.') . (!empty($data['error']) ? " error={$data['error']}" : ""), "white");
    }
    foreach ($calls as $phone) {
        try {
            $phone->unRegister();
            $phone->close();

        } catch (Throwable $e) {
        }
    }
    \Swoole\Timer::clear($timerId);
    cli::pcl("Benchmark finalizado", "green");
});
