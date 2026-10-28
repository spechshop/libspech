<?php

/**
 * Technical benchmark with audio quality and metrics analysis for libspech
 *
 * This script runs concurrent SIP calls, records audio and collects media
 * statistics using mediaChannel->getAudioMetrics(). Each call yields a
 * quality score combining RMS audio level, packet loss and jitter. An
 * evaluation note per call is computed from success, setup time and the
 * quality score. At the end, summary statistics and averages are printed.
 * Scores displayed on the MOS scale are heuristic estimates, not perceptual MOS measurements.
 */

ini_set('memory_limit', '1024M');

use libspech\Cli\cli;
use libspech\Sip\trunkController;
use Swoole\Coroutine;
use Swoole\Runtime;
use function libspech\Sip\interruptibleSleep;
use function Swoole\Coroutine\run;

function computeAudioQualityScore(string $filePath): float
{
    if (!file_exists($filePath)) {
        throw new RuntimeException("WAV não encontrado: {$filePath}");
    }
    $data = file_get_contents($filePath);
    if ($data === false || strlen($data) < 44) {
        throw new RuntimeException("WAV vazio ou ilegível: {$filePath}");
    }
    $pcm = substr($data, 44);
    $sampleCount = intdiv(strlen($pcm), 2);
    if ($sampleCount === 0) {
        throw new RuntimeException("WAV sem amostras: {$filePath}");
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
    $totalCalls  = 3;
    $durationSec = 30;
    $username    = getenv('SIP_USERNAME') ?: '';
    $password    = getenv('SIP_PASSWORD') ?: '';
    $domain      = getenv('SIP_HOST') ?: 'spechshop.com';
    $host        = getenv('SIP_HOST') ?: 'spechshop.com';
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
            $phone   = new trunkController($username, $password, $host, 5060,$host);
            $phone->setSipIpVersion(4);
            $phone->mountLineCodecSDP('PCMA/8000');
            $phone->enableStereoSound();
            $phone->setPacketTime(20);
            $phone->enableAudioMemorySharing();
            $phone->enableAudioRecording();
            $phone->setCallerId('testbench');




            $audioFile = "/home/lotus/Músicas/5.-Kant-Spinardi-Nefarius-nrdc50.wav";


            $phone->defineAudioFile($audioFile);



            $stats[$callKey] = [
                'cid'            => $phone->callId,
                'answered'       => false,
                'failed'         => false,
                'finished'       => false,
                'registration_time' => 0.0,
                'seconds'        => 0.0,
                'bytes'          => 0,
                'error'          => null,
                'started_at'     => null,
                'media_started_at' => null,
                'ended_at'       => null,
                'audio_file'     => $audioFile,
                'output_rec'     => "benchmark/rec_$i.wav",
                'audio_quality'  => null,
                'evaluation'     => null,
                'metrics'        => [],
            ];
            $calls[$callKey] = $phone;
            $audioProcessed = false;
            $finalizeAudio = function (trunkController $phone) use ($callKey, &$stats, &$audioProcessed) {
                if ($audioProcessed || $stats[$callKey]['media_started_at'] === null) {
                    return;
                }
                $audioProcessed = true;

                try {
                    try {
                        $stats[$callKey]['metrics'] = $phone->mediaChannel->getAudioMetrics();
                    } catch (Throwable $ex) {
                        $stats[$callKey]['metrics'] = [];
                    }

                    $audioBuffer = (string)$phone->getBuffer();
                    $stats[$callKey]['bytes'] = strlen($audioBuffer);
                    $phone->saveBufferToWavFile($stats[$callKey]['output_rec'], $audioBuffer);

                    if ($stats[$callKey]['started_at'] === null) {
                        $stats[$callKey]['started_at'] = $stats[$callKey]['media_started_at'];
                    }
                    if ($stats[$callKey]['ended_at'] === null) {
                        $stats[$callKey]['ended_at'] = microtime(true);
                    }
                    $stats[$callKey]['seconds'] = max(
                        0.0,
                        $stats[$callKey]['ended_at'] - $stats[$callKey]['started_at']
                    );

                    $qualityScore = computeAudioQualityScore($stats[$callKey]['output_rec']);
                    $metrics = $stats[$callKey]['metrics'];
                    if (isset($metrics['lost_packets']) && $metrics['lost_packets'] > 0) {
                        $qualityScore -= min(2.0, $metrics['lost_packets'] / 10.0);
                    }
                    if (isset($metrics['jitter_ms'])) {
                        if ($metrics['jitter_ms'] > 2.0) {
                            $qualityScore -= 2.0;
                        } elseif ($metrics['jitter_ms'] > 1.0) {
                            $qualityScore -= 1.0;
                        }
                    }
                    $qualityScore = max(0.0, min(10.0, $qualityScore));
                    $stats[$callKey]['audio_quality'] = round($qualityScore, 1);

                    if ($stats[$callKey]['answered']) {
                        $registrationTime = $stats[$callKey]['registration_time'];
                        $registrationScore = ($registrationTime < 0.5) ? 10.0 : (($registrationTime < 1.0) ? 8.0 : (($registrationTime < 1.5) ? 6.0 : 4.0));
                        $stats[$callKey]['evaluation'] = round(10.0 * 0.4 + $registrationScore * 0.2 + $qualityScore * 0.4, 1);
                    }
                    cli::pcl("[{$callKey}] Áudio salvo e analisado ({$stats[$callKey]['bytes']} bytes)", "green");
                } catch (Throwable $ex) {
                    if ($stats[$callKey]['error'] === null) {
                        $stats[$callKey]['error'] = $ex->getMessage();
                    }
                    cli::pcl("[{$callKey}] Erro ao salvar/analisar áudio: {$ex->getMessage()}", "red");
                }
            };

            $registerStart = microtime(true);
            $registered    = $phone->register(10);
            if (!$registered) {
                $stats[$callKey]['failed']   = true;
                $stats[$callKey]['error']    = 'Erro ao registrar';
                $stats[$callKey]['finished'] = true;
                cli::pcl("[{$callKey}] Erro ao registrar", "red");
                return;
            }
            $stats[$callKey]['registration_time'] = microtime(true) - $registerStart;
            cli::pcl("[{$callKey}] Registrado em " . round($stats[$callKey]['registration_time'], 3) . "s", "green");

            $phone->onRinging(function () use ($callKey) {
                cli::pcl("[{$callKey}] Tocando", "yellow");
            });
            $phone->onSdpReceived(function (trunkController $phone) use ($callKey, &$stats) {
                cli::pcl("[{$callKey}] SDP recebido", "blue");
                $phone->receiveMedia();
                $stats[$callKey]['media_started_at'] = microtime(true);
                $phone->mediaChannel->setAudioMetricsEnabled(true);
                $phone->mediaChannel->connectTimeout=120;
            });
            $phone->onAnswer(function (trunkController $phone) use (
                $callKey,
                $durationSec,
                &$stats,
                $finalizeAudio
            ) {


                cli::pcl("[{$callKey}] Atendida", "green");
                $stats[$callKey]['answered']   = true;
                $stats[$callKey]['started_at'] = microtime(true);

                try {



                    \libspech\Sip\interruptibleSleep($durationSec, $phone->receiveBye);

                    $stats[$callKey]['ended_at'] = microtime(true);
                    $stats[$callKey]['seconds'] = $stats[$callKey]['ended_at'] - $stats[$callKey]['started_at'];
                    $finalizeAudio($phone);

                    $phone->bye();
                    $phone->unRegister();
                    $phone->close();
                    cli::pcl("[{$callKey}] Encerrando após {$durationSec}s", "yellow");
                } catch (Throwable $ex) {
                    $stats[$callKey]['failed'] = true;
                    $stats[$callKey]['error'] = $ex->getMessage();
                    cli::pcl("[{$callKey}] Erro na avaliação: {$ex->getMessage()}", "red");
                    $phone->close();
                } finally {
                    // WAV writing yields to other coroutines; publish completion only after scoring.
                    $stats[$callKey]['finished'] = true;
                }

            });
            $phone->onFailed(function ($message) use ($callKey, &$stats, $phone, $finalizeAudio) {
                $stats[$callKey]['failed']    = true;
                $stats[$callKey]['error']     = $message;
                $finalizeAudio($phone);
                if (!$stats[$callKey]['answered']) {
                    $stats[$callKey]['finished'] = true;
                }
                cli::pcl("[{$callKey}] Falhou: {$message}", "red");
                $phone->close();
            });
            $phone->onHangup(function (trunkController $phone) use ($callKey, &$stats, $finalizeAudio) {
                cli::pcl("[{$callKey}] BYE recebido", "red");
                $finalizeAudio($phone);
                if ($stats[$callKey]['started_at'] && !$stats[$callKey]['ended_at']) {
                    $stats[$callKey]['ended_at'] = microtime(true);
                    $stats[$callKey]['seconds']  = $stats[$callKey]['ended_at'] - $stats[$callKey]['started_at'];
                }
                $stats[$callKey]['finished'] = true;
                $phone->unRegister();
            });
            $phone->onPacketOnTimeoutMedia(function ($peer) use ($phone, $callKey, &$stats, $finalizeAudio) {
                cli::pcl("[{$callKey}] Timeout de mídia", "bold_red");
                $stats[$callKey]['failed']    = true;
                $stats[$callKey]['error']     = 'Timeout de mídia';
                $finalizeAudio($phone);
                if (!$stats[$callKey]['answered']) {
                    $stats[$callKey]['finished'] = true;
                }
                $phone->bye();
                $phone->close();
                return true;
            });
            cli::pcl("[{$callKey}] Ligando para {$destination}", "cyan");


            $phone->call($destination);





        });
        if ($i ==1) Coroutine::sleep(5);
        else Coroutine::sleep(0.2);
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
    $registrationTimes = [];
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
        $registrationTimes[] = $data['registration_time'];
        if ($data['evaluation'] !== null) {
            $evaluations[] = $data['evaluation'];
        }
        if (!empty($data['metrics'])) {
            $m = $data['metrics'];
            if (isset($m['lost_packets'])) {
                $totalLostPackets += (int)$m['lost_packets'];
            }
            if (isset($m['total_packets'])) {
                $totalPackets += (int)$m['total_packets'];
            }
            if (isset($m['jitter_ms'])) {
                $totalJitter += (float)$m['jitter_ms'];
            }
            $metricsCount++;
        }
    }
    $endBenchmark       = microtime(true);
    $totalBenchmarkTime = $endBenchmark - $benchmarkStart;
    $callsPerSecond     = $totalCalls / $totalBenchmarkTime;
    $avgRegistrationTime = count($registrationTimes) > 0 ? array_sum($registrationTimes) / count($registrationTimes) : 0.0;
    $minRegistrationTime = count($registrationTimes) > 0 ? min($registrationTimes) : 0.0;
    $maxRegistrationTime = count($registrationTimes) > 0 ? max($registrationTimes) : 0.0;
    $averageEvaluation  = count($evaluations) > 0 ? array_sum($evaluations) / count($evaluations) : null;

    $avgLostPct  = 0.0;
    $avgJitter   = 0.0;
    $expectedPackets = $totalPackets + $totalLostPackets;
    if ($expectedPackets > 0) {
        $avgLostPct = ($totalLostPackets / $expectedPackets) * 100.0;
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
        "Tempo de registro SIP médio: " . round($avgRegistrationTime, 3) . "s (min: " . round($minRegistrationTime, 3) . "s, max: " . round($maxRegistrationTime, 3) . "s)",
        "cyan"
    );
    $averageEvaluationMos = $averageEvaluation !== null ? round(1.0 + ($averageEvaluation * 0.4), 2) . '/5' : 'N/A';
    cli::pcl("Média da estimativa heurística (escala MOS; não perceptual): {$averageEvaluationMos}", "bold_green");
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
        $registration = round($data['registration_time'], 3);
        $audioQuality = $data['audio_quality'] !== null ? round(1.0 + ($data['audio_quality'] * 0.4), 1) . '/5' : 'N/A';
        $eval         = $data['evaluation'] !== null ? round(1.0 + ($data['evaluation'] * 0.4), 1) . '/5' : 'N/A';
        $lost         = isset($data['metrics']['lost_packets']) ? $data['metrics']['lost_packets'] : 'N/A';
        $totalPkt     = isset($data['metrics']['total_packets']) ? $data['metrics']['total_packets'] : 'N/A';
        $jitter       = isset($data['metrics']['jitter_ms']) ? round($data['metrics']['jitter_ms'], 3) : 'N/A';
        cli::pcl(
            "[{$callKey}] $data[cid] answered=" . ($data['answered'] ? 'sim' : 'nao') .
            " failed=" . ($data['failed'] ? 'sim' : 'nao') .
            " seconds={$seconds}" .
            " registro_sip={$registration}s" .
            " perda_estimada={$lost}/" . ((int)$lost + (int)$totalPkt) .
            " jitter_ms={$jitter}" .
            " qualidade_audio_heuristica_escala_MOS={$audioQuality}" .
            " nota_heuristica_escala_MOS={$eval}" .
            (!empty($data['error']) ? " error={$data['error']}" : ''),
            "white"
        );
    }

    cli::pcl("Benchmark finalizado", "green");
    printAudioCacheReport('FINAL');
});

function printAudioCacheReport(string $label): void
{
    cli::pcl("=== AUDIO CACHE REPORT: {$label} ===", "bold_cyan");

    $rawCache = \libspech\Sip\trunkController::$sharedAudioCache ?? [];
    cli::pcl("Raw WAV cache items: " . count($rawCache), "cyan");

    foreach ($rawCache as $key => $item) {
        $audioLen  = $item['audioLen'] ?? 0;
        $chunkSize = $item['chunkSize'] ?? 0;
        $rate      = $item['infoFile']['rate'] ?? 'N/A';
        $channels  = $item['infoFile']['numChannels'] ?? 'N/A';
        $bitDepth  = $item['infoFile']['bitDepth'] ?? 'N/A';

        cli::pcl(
            "RAW key=" . substr((string)$key, 0, 12) .
            " audioLen={$audioLen}" .
            " chunkSize={$chunkSize}" .
            " rate={$rate}" .
            " channels={$channels}" .
            " bitDepth={$bitDepth}",
            "white"
        );
    }

    $encodedCache = \libspech\Cache\cache::get('libspechAudioEncodedCache');
    $building     = \libspech\Cache\cache::get('libspechAudioEncodedBuilding');

    $encodedCache = is_array($encodedCache) ? $encodedCache : [];
    $building     = is_array($building) ? $building : [];

    cli::pcl("Encoded cache items: " . count($encodedCache), "cyan");
    cli::pcl("Encoded cache building locks: " . count($building), "cyan");

    foreach ($encodedCache as $key => $item) {
        $chunks     = isset($item['chunks']) && is_array($item['chunks']) ? count($item['chunks']) : 0;
        $frameCount = $item['frameCount'] ?? 0;
        $codec      = $item['codec'] ?? 'N/A';
        $frequency  = $item['frequency'] ?? 'N/A';
        $channels   = $item['channels'] ?? 'N/A';
        $chunkSize  = $item['chunkSize'] ?? 'N/A';
        $complete   = !empty($item['complete']) ? 'sim' : 'nao';
        $createdAt  = (int)($item['createdAt'] ?? 0);
        $lastUsed   = (int)($item['lastUsed'] ?? 0);

        $hitStatus = 'inconclusivo';
        if ($lastUsed > $createdAt) {
            $hitStatus = 'sim';
        } elseif ($complete === 'sim' && $chunks > 0) {
            $hitStatus = 'cache criado, hit nao provado';
        }

        cli::pcl(
            "ENC key=" . substr((string)$key, 0, 12) .
            " codec={$codec}" .
            " freq={$frequency}" .
            " channels={$channels}" .
            " chunkSize={$chunkSize}" .
            " complete={$complete}" .
            " chunks={$chunks}" .
            " frameCount={$frameCount}" .
            " createdAt={$createdAt}" .
            " lastUsed={$lastUsed}" .
            " hit={$hitStatus}",
            "white"
        );
    }

    cli::pcl("================================", "bold_cyan");
}
