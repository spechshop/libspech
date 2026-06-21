<?php

/**
 * Technical benchmark with audio quality and metrics analysis for libspech
 *
 * This script runs concurrent SIP calls, records audio and collects media
 * statistics using mediaChannel->getAudioMetrics(). Each call yields a
 * quality score combining RMS audio level, packet loss and jitter. An
 * evaluation note per call is computed from success, setup time and the
 * quality score. At the end, summary statistics and averages are printed.
 */

ini_set('memory_limit', '1024M');

use libspech\Cli\cli;
use libspech\Sip\trunkController;
use Swoole\Coroutine;
use Swoole\Runtime;
use function Swoole\Coroutine\run;

function computeAudioQualityScore(string $filePath): float
{
    if (!file_exists($filePath)) {
        return 0.0;
    }
    $data = file_get_contents($filePath);
    if ($data === false || strlen($data) < 44) {
        return 0.0;
    }
    $pcm = substr($data, 44);
    $sampleCount = intdiv(strlen($pcm), 2);
    if ($sampleCount === 0) {
        return 0.0;
    }
    $sumSquares = 0.0;
    for ($i = 0; $i < $sampleCount; $i++) {
        $sample = unpack('s', $pcm[$i * 2] . $pcm[$i * 2 + 1])[1];
        $sumSquares += $sample * $sample;
    }
    $rms = sqrt($sumSquares / $sampleCount) / 32768.0;
    if ($rms < 0.02) {
        return 4.0;
    } elseif ($rms < 0.05) {
        return 6.0;
    } elseif ($rms < 0.20) {
        return 10.0;
    } elseif ($rms < 0.40) {
        return 8.0;
    } else {
        return 5.0;
    }
}

Runtime::enableCoroutine();

include 'plugins/autoloader.php';

run(function () {
    if (!is_dir('benchmark')) {
        mkdir('benchmark', 0755, true);
    }
    shell_exec('rm benchmark/*');
    $totalCalls  = 50;
    $durationSec = 20;
    $username    = getenv('SIP_USERNAME') ?: '';
    $password    = getenv('SIP_PASSWORD') ?: '';
    $domain      = getenv('SIP_HOST') ?: 'spechshop.com';
    $host        = filter_var($domain, FILTER_VALIDATE_IP) ? $domain : gethostbyname($domain);
    $destination = '553140040104';

    $calls = [];
    $stats = [];

    $benchmarkStart = microtime(true);

    cli::pcl("=== Benchmark com Análise Avançada de Áudio ===", "bold_cyan");
    cli::pcl("Chamadas planejadas: {$totalCalls}", "yellow");
    cli::pcl("Duração por chamada: {$durationSec}s", "yellow");
    cli::pcl("Servidor SIP: {$host}", "yellow");

    for ($i = 1; $i <= $totalCalls; $i++) {
        Coroutine::create(function () use (
            $i,
            $username,
            $password,
            $host,
            $destination,
            $durationSec,
            &$calls,
            &$stats
        ) {
            $callKey = "call_{$i}";
            $phone   = new trunkController($username, $password, $host);
            $phone->mountLineCodecSDP('PCMU/8000');
            $phone->enableAudioMemorySharing();
            $phone->enableAudioRecording();




            $audioFile = "ss.wav";
            $phone->defineAudioFile($audioFile);

            $stats[$callKey] = [
                'answered'       => false,
                'failed'         => false,
                'finished'       => false,
                'setup_time'     => 0.0,
                'seconds'        => 0.0,
                'bytes'          => 0,
                'error'          => null,
                'started_at'     => null,
                'ended_at'       => null,
                'audio_file'     => $audioFile,
                'output_rec'     => "benchmark/rec_$i.wav",
                'audio_quality'  => 0.0,
                'evaluation'     => 0.0,
                'metrics'        => [],
            ];
            $calls[$callKey] = $phone;

            $registerStart = microtime(true);
            $registered    = $phone->register(2);
            if (!$registered) {
                $stats[$callKey]['failed']   = true;
                $stats[$callKey]['error']    = 'Erro ao registrar';
                $stats[$callKey]['finished'] = true;
                cli::pcl("[{$callKey}] Erro ao registrar", "red");
                return;
            }
            $stats[$callKey]['setup_time'] = microtime(true) - $registerStart;
            cli::pcl("[{$callKey}] Registrado em " . round($stats[$callKey]['setup_time'], 3) . "s", "green");

            $phone->onRinging(function () use ($callKey) {
                cli::pcl("[{$callKey}] Tocando", "yellow");
            });
            $phone->onSdpReceived(function (trunkController $phone) use ($callKey) {
                cli::pcl("[{$callKey}] SDP recebido", "blue");
                $phone->receiveMedia();
            });
            $phone->onAnswer(function (trunkController $phone) use (
                $callKey,
                $durationSec,
                &$stats
            ) {
                cli::pcl("[{$callKey}] Atendida", "green");
                $stats[$callKey]['answered']   = true;
                $stats[$callKey]['started_at'] = microtime(true);



                \libspech\Sip\interruptibleSleep($durationSec/2, $phone->receiveBye);
                $phone->send2833('*');
                \libspech\Sip\interruptibleSleep($durationSec/2, $phone->receiveBye);
                $buffer=$phone->getBuffer();

                // Capture media metrics before closing
                try {
                    $metrics = $phone->mediaChannel->getAudioMetrics();
                } catch (Throwable $ex) {
                    $metrics = [];
                }
                $stats[$callKey]['metrics'] = $metrics;

                $stats[$callKey]['ended_at'] = microtime(true);
                $stats[$callKey]['seconds']  = $stats[$callKey]['ended_at'] - $stats[$callKey]['started_at'];
                $stats[$callKey]['finished'] = true;
                $stats[$callKey]['bytes']    = $buffer->length();
                $phone->saveBufferToWavFile($stats[$callKey]['output_rec'], $buffer);

                // Analyse audio file
                $audioFilePath = $stats[$callKey]['output_rec'];
                $qualityScore  = computeAudioQualityScore($audioFilePath);

                // Adjust quality based on audio metrics
                if (isset($metrics['lost_packets']) && $metrics['lost_packets'] > 0) {
                    $qualityScore -= min(2.0, $metrics['lost_packets'] / 10.0);
                }
                if (isset($metrics['jitter'])) {
                    $jitter = $metrics['jitter'];
                    if ($jitter > 2.0) {
                        $qualityScore -= 2.0;
                    } elseif ($jitter > 1.0) {
                        $qualityScore -= 1.0;
                    }
                }
                $qualityScore = max(0.0, min(10.0, $qualityScore));
                $stats[$callKey]['audio_quality'] = round($qualityScore, 1);

                // Compute evaluation score
                $successScore = 10.0;
                $setupTime    = $stats[$callKey]['setup_time'];
                $setupScore   = ($setupTime < 0.5) ? 10.0 : (($setupTime < 1.0) ? 8.0 : (($setupTime < 1.5) ? 6.0 : 4.0));
                $stats[$callKey]['evaluation'] = round($successScore * 0.4 + $setupScore * 0.2 + $qualityScore * 0.4, 1);



                $phone->bye();
                $phone->unRegister();
                $phone->close();
                cli::pcl("[{$callKey}] Encerrando após {$durationSec}s", "yellow");



            });
            $phone->onFailed(function ($message) use ($callKey, &$stats, $phone) {
                $stats[$callKey]['failed']    = true;
                $stats[$callKey]['error']     = $message;
                $stats[$callKey]['finished']  = true;
                $stats[$callKey]['evaluation'] = 0.0;
                cli::pcl("[{$callKey}] Falhou: {$message}", "red");
                $phone->close();
            });
            $phone->onHangup(function (trunkController $phone) use ($callKey, &$stats) {
                cli::pcl("[{$callKey}] BYE recebido", "red");
                if ($stats[$callKey]['started_at'] && !$stats[$callKey]['ended_at']) {
                    $stats[$callKey]['ended_at'] = microtime(true);
                    $stats[$callKey]['seconds']  = $stats[$callKey]['ended_at'] - $stats[$callKey]['started_at'];
                }
                $stats[$callKey]['finished'] = true;
                $phone->unRegister();
            });
            $phone->onPacketOnTimeoutMedia(function ($peer) use ($phone, $callKey, &$stats) {
                cli::pcl("[{$callKey}] Timeout de mídia", "bold_red");
                $stats[$callKey]['failed']    = true;
                $stats[$callKey]['error']     = 'Timeout de mídia';
                $stats[$callKey]['finished']  = true;
                $stats[$callKey]['evaluation'] = 0.0;
                $phone->bye();
                $phone->close();
                return true;
            });
            cli::pcl("[{$callKey}] Ligando para {$destination}", "cyan");
            $phone->call($destination);


            $phone->saveBufferToWavFile($stats[$callKey]['output_rec'], $phone->getBuffer());


        });
        Coroutine::sleep(0.1);
    }

    // Periodic resource reporting
    $lastCpuTime  = 0;
    $lastWallTime = microtime(true);
    $timerId = \Swoole\Timer::tick(5000, function () use (&$lastCpuTime, &$lastWallTime, &$stats, $totalCalls) {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak  = memory_get_peak_usage(true);
        $now         = microtime(true);

        cli::pcl("--- DEBUG de Recursos ---", "bold_cyan");
        cli::pcl("Memória atual: " . round($memoryUsage / 1024 / 1024, 2) . " MB", "cyan");
        cli::pcl("Memória pico: " . round($memoryPeak / 1024 / 1024, 2) . " MB", "cyan");

        if (file_exists('/proc/self/stat')) {
            $stat  = file_get_contents('/proc/self/stat');
            $parts = explode(' ', $stat);
            if (count($parts) >= 15) {
                $utime         = intval($parts[13]);
                $stime         = intval($parts[14]);
                $totalCpuTime  = $utime + $stime;
                $clkTck        = 100;
                $deltaCpu      = $totalCpuTime - $lastCpuTime;
                $deltaTime     = $now - $lastWallTime;
                $cpuUsagePct   = 0;
                if ($deltaTime > 0) {
                    $cpuUsagePct = ($deltaCpu / $clkTck) / $deltaTime * 100;
                }
                cli::pcl("CPU Usage: " . round($cpuUsagePct, 2) . "%", "bold_yellow");
                cli::pcl("CPU user: " . round($utime / $clkTck, 2) . "s | system: " . round($stime / $clkTck, 2) . "s", "cyan");
                $lastCpuTime  = $totalCpuTime;
                $lastWallTime = $now;
            }
        }
        $finished = 0;
        foreach ($stats as $data) {
            if (!empty($data['finished'])) {
                $finished++;
            }
        }
        cli::pcl("Chamadas concluídas: {$finished}/{$totalCalls}", "cyan");
        cli::pcl("---------------------------", "bold_cyan");
    });

    cli::pcl("Aguardando a conclusão de todas as chamadas...", "yellow");
    $startWait = time();
    $timeout   = $durationSec + 90;
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
    \Swoole\Timer::clear($timerId);

    $answeredCalls = 0;
    $failedCalls   = 0;
    $totalSeconds  = 0.0;
    $setupTimes    = [];
    $evaluations   = [];
    // aggregate metrics
    $totalLostPackets = 0;
    $totalPackets     = 0;
    $totalJitter      = 0.0;
    $metricsCount     = 0;

    foreach ($stats as $data) {
        if ($data['answered']) {
            $answeredCalls++;
            $totalSeconds += $data['seconds'];
        }
        if ($data['failed']) {
            $failedCalls++;
        }
        $setupTimes[]  = $data['setup_time'];
        $evaluations[] = $data['evaluation'];
        if (!empty($data['metrics'])) {
            $m = $data['metrics'];
            if (isset($m['lost_packets'])) {
                $totalLostPackets += (int)$m['lost_packets'];
            }
            if (isset($m['total_packets'])) {
                $totalPackets += (int)$m['total_packets'];
            }
            if (isset($m['jitter'])) {
                $totalJitter += (float)$m['jitter'];
            }
            $metricsCount++;
        }
    }
    $endBenchmark       = microtime(true);
    $totalBenchmarkTime = $endBenchmark - $benchmarkStart;
    $callsPerSecond     = $totalCalls / $totalBenchmarkTime;
    $avgSetupTime       = count($setupTimes) > 0 ? array_sum($setupTimes) / count($setupTimes) : 0.0;
    $minSetupTime       = count($setupTimes) > 0 ? min($setupTimes) : 0.0;
    $maxSetupTime       = count($setupTimes) > 0 ? max($setupTimes) : 0.0;
    $averageEvaluation  = count($evaluations) > 0 ? array_sum($evaluations) / count($evaluations) : 0.0;

    $avgLostPct  = 0.0;
    $avgJitter   = 0.0;
    if ($totalPackets > 0) {
        $avgLostPct = ($totalLostPackets / $totalPackets) * 100.0;
    }
    if ($metricsCount > 0) {
        $avgJitter = $totalJitter / $metricsCount;
    }

    $finalMemory    = memory_get_usage(true);
    $finalMemoryMax = memory_get_peak_usage(true);
    $finalCpuUser   = 0.0;
    $finalCpuSystem = 0.0;
    if (file_exists('/proc/self/stat')) {
        $stat  = file_get_contents('/proc/self/stat');
        $parts = explode(' ', $stat);
        if (count($parts) >= 15) {
            $utime = intval($parts[13]);
            $stime = intval($parts[14]);
            $clkTck = 100;
            $finalCpuUser   = round($utime / $clkTck, 2);
            $finalCpuSystem = round($stime / $clkTck, 2);
        }
    }

    cli::pcl("==============================", "cyan");
    cli::pcl("Resumo Técnico com Métricas", "bold_green");
    cli::pcl("Chamadas planejadas: {$totalCalls}", "green");
    cli::pcl("Chamadas atendidas: {$answeredCalls}", "green");
    cli::pcl("Chamadas falhas: {$failedCalls}", "yellow");
    cli::pcl("Tempo total: " . round($totalBenchmarkTime, 2) . "s", "bold_yellow");
    cli::pcl("Throughput: " . round($callsPerSecond, 2) . " chamadas/s", "bold_yellow");
    cli::pcl(
        "Setup time médio: " . round($avgSetupTime, 3) . "s (min: " . round($minSetupTime, 3) . "s, max: " . round($maxSetupTime, 3) . "s)",
        "cyan"
    );
    cli::pcl("Média das notas de avaliação: " . round($averageEvaluation, 2) . "/10", "bold_green");
    if ($metricsCount > 0) {
        cli::pcl("Perda média de pacotes: " . round($avgLostPct, 2) . "%", "cyan");
        cli::pcl("Jitter médio: " . round($avgJitter, 3) . " ms", "cyan");
    }
    cli::pcl("Memória final: " . round($finalMemory / 1024 / 1024, 2) . " MB", "yellow");
    cli::pcl("Pico de memória: " . round($finalMemoryMax / 1024 / 1024, 2) . " MB", "yellow");
    cli::pcl("CPU user: {$finalCpuUser}s | system: {$finalCpuSystem}s", "yellow");
    cli::pcl("==============================", "cyan");

    foreach ($stats as $callKey => $data) {
        $seconds      = round($data['seconds'], 2);
        $setup        = round($data['setup_time'], 3);
        $audioQuality = round($data['audio_quality'], 1);
        $eval         = round($data['evaluation'], 1);
        $lost         = isset($data['metrics']['lost_packets']) ? $data['metrics']['lost_packets'] : 'N/A';
        $totalPkt     = isset($data['metrics']['total_packets']) ? $data['metrics']['total_packets'] : 'N/A';
        $jitter       = isset($data['metrics']['jitter']) ? round($data['metrics']['jitter'], 3) : 'N/A';
        cli::pcl(
            "[{$callKey}] answered=" . ($data['answered'] ? 'sim' : 'nao') .
            " failed=" . ($data['failed'] ? 'sim' : 'nao') .
            " seconds={$seconds}" .
            " setup={$setup}s" .
            " loss={$lost}/{$totalPkt}" .
            " jitter={$jitter}" .
            " qualidade_audio={$audioQuality}/10" .
            " nota={$eval}/10" .
            (!empty($data['error']) ? " error={$data['error']}" : ''),
            "white"
        );
    }

    cli::pcl("Benchmark finalizado", "green");
});








