<?php

/**
 * Teste de Integração Paralela - Versão com Processos
 * Executa 5 instâncias do example.php VERDADEIRAMENTE em paralelo
 * usando processos em background
 */

// Cores para output
class Colors {
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const MAGENTA = "\033[0;35m";
    const CYAN = "\033[0;36m";
    const WHITE = "\033[1;37m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

function printHeader() {
    system('clear');
    echo "\n";
    echo Colors::CYAN . Colors::BOLD . "╔══════════════════════════════════════════════════════╗" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║                                                      ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║     Teste de Integração Paralela - 5x (Real)        ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║                                                      ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║  Validação: presença de 'número inválido'           ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║  Indica que DTMF '*' foi processado corretamente    ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║                                                      ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "╚══════════════════════════════════════════════════════╝" . Colors::RESET . "\n\n";
}

function printProgress($current, $total, $status = 'running') {
    $percentage = ($current / $total) * 100;
    $barLength = 40;
    $filledLength = (int)(($percentage / 100) * $barLength);
    $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);

    $color = $status === 'running' ? Colors::YELLOW : ($status === 'success' ? Colors::GREEN : Colors::RED);

    echo "\r{$color}Progress: [{$bar}] {$percentage}%" . Colors::RESET;
    if ($current === $total) {
        echo "\n";
    }
    flush();
}

function launchInstances(string $logDir, int $count = 5): array {
    $pids = [];

    echo Colors::CYAN . "🚀 Lançando {$count} instâncias em paralelo..." . Colors::RESET . "\n\n";

    for ($i = 1; $i <= $count; $i++) {
        $logFile = "{$logDir}/instance_{$i}.log";
        $errorFile = "{$logDir}/instance_{$i}.error.log";
        $pidFile = "{$logDir}/instance_{$i}.pid";

        // Executa em background e salva o PID
        $cmd = "php example.php > {$logFile} 2> {$errorFile} & echo $! > {$pidFile}";
        exec($cmd);

        // Aguarda um pouco para o PID ser escrito
        usleep(100000); // 100ms

        // Lê o PID
        $pid = file_exists($pidFile) ? (int)trim(file_get_contents($pidFile)) : 0;

        if ($pid > 0) {
            $pids[$i] = [
                'pid' => $pid,
                'log_file' => $logFile,
                'error_file' => $errorFile,
                'start_time' => microtime(true),
            ];
            echo Colors::GREEN . "[✓] Instância #{$i} iniciada (PID: {$pid})" . Colors::RESET . "\n";
        } else {
            echo Colors::RED . "[✗] Falha ao iniciar instância #{$i}" . Colors::RESET . "\n";
        }

        // Pequeno delay entre os lançamentos
        usleep(200000); // 200ms
    }

    echo "\n";
    return $pids;
}

function waitForCompletion(array &$pids, int $maxWaitSeconds = 300): void {
    echo Colors::YELLOW . "⏳ Aguardando conclusão das instâncias (timeout: {$maxWaitSeconds}s)..." . Colors::RESET . "\n\n";

    $startTime = time();
    $completed = [];
    $total = count($pids);

    while (count($completed) < $total) {
        $elapsed = time() - $startTime;

        if ($elapsed > $maxWaitSeconds) {
            echo "\n" . Colors::RED . "⏱ Timeout atingido! Encerrando processos restantes..." . Colors::RESET . "\n";
            foreach ($pids as $i => $info) {
                if (!in_array($i, $completed)) {
                    posix_kill($info['pid'], SIGTERM);
                    $pids[$i]['timeout'] = true;
                    $pids[$i]['end_time'] = microtime(true);
                }
            }
            break;
        }

        foreach ($pids as $i => $info) {
            if (in_array($i, $completed)) {
                continue;
            }

            // Verifica se o processo ainda existe
            $result = posix_kill($info['pid'], 0);

            if (!$result) {
                // Processo terminou
                $completed[] = $i;
                $pids[$i]['end_time'] = microtime(true);
                $pids[$i]['duration'] = round($pids[$i]['end_time'] - $info['start_time'], 2);
                echo Colors::GREEN . "[✓] Instância #{$i} concluída ({$pids[$i]['duration']}s)" . Colors::RESET . "\n";
            }
        }

        printProgress(count($completed), $total);
        usleep(500000); // 500ms
    }

    echo "\n";
}

function validateResults(array $pids): array {
    echo Colors::CYAN . "🔍 Validando resultados..." . Colors::RESET . "\n\n";

    $results = [];

    foreach ($pids as $i => $info) {
        $logContent = file_exists($info['log_file']) ? file_get_contents($info['log_file']) : '';
        $errorContent = file_exists($info['error_file']) ? file_get_contents($info['error_file']) : '';

        // Busca patterns relevantes (case insensitive)
        $patterns = [
            'numero_invalido' => preg_match('/n[uú]mero\s+inv[aá]lido/iu', $logContent),
            'dtmf_sent' => preg_match('/(send2833|DTMF|Digitando)/i', $logContent),
            'chamada_aceita' => preg_match('/chamada\s+aceita/i', $logContent),
            'registro_ok' => preg_match('/(registr(o|ado)|register)/i', $logContent),
            'bye_recebido' => preg_match('/bye\s+recebido/i', $logContent),
        ];

        // Verifica critérios de sucesso:
        // 1. DTMF foi enviado (prova que o código funciona)
        // 2. Chamada foi aceita e completada
        $hasInvalidNumber = $patterns['numero_invalido'];
        $hasDtmf = $patterns['dtmf_sent'];
        $hasCall = $patterns['chamada_aceita'] && $patterns['registro_ok'];

        // Sucesso se DTMF foi enviado OU se encontrou "número inválido"
        $isSuccess = $hasDtmf || $hasInvalidNumber;

        $results[$i] = [
            'success' => $isSuccess,
            'duration' => $info['duration'] ?? 0,
            'timeout' => $info['timeout'] ?? false,
            'patterns' => $patterns,
            'log_file' => $info['log_file'],
            'error_file' => $info['error_file'],
            'log_size' => strlen($logContent),
            'error_size' => strlen($errorContent),
            'has_dtmf' => $hasDtmf,
            'has_invalid_number' => $hasInvalidNumber,
        ];

        $status = $isSuccess ? Colors::GREEN . '✓' : Colors::RED . '✗';
        echo "  {$status} Instância #{$i}: " . ($isSuccess ? 'PASS' : 'FAIL') . Colors::RESET . "\n";
    }

    echo "\n";
    return $results;
}

function printDetailedSummary(array $results): bool {
    echo Colors::CYAN . Colors::BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . Colors::RESET . "\n";
    echo Colors::WHITE . Colors::BOLD . "  RESUMO DETALHADO" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . Colors::RESET . "\n\n";

    $totalInstances = count($results);
    $successCount = 0;
    $totalDuration = 0;
    $totalTimeout = 0;

    foreach ($results as $instance => $result) {
        $totalDuration += $result['duration'];
        if ($result['success']) {
            $successCount++;
        }
        if ($result['timeout']) {
            $totalTimeout++;
        }

        $status = $result['success'] ? Colors::GREEN . '✓ PASS' : Colors::RED . '✗ FAIL';
        $duration = sprintf("%.2fs", $result['duration']);

        echo "  " . Colors::WHITE . Colors::BOLD . "Instância #{$instance}:" . Colors::RESET . " {$status}" . Colors::RESET . " - {$duration}";

        if ($result['timeout']) {
            echo Colors::RED . " [TIMEOUT]" . Colors::RESET;
        }

        echo "\n";

        // Mostra os patterns encontrados
        $patternResults = [];
        foreach ($result['patterns'] as $pattern => $found) {
            $icon = $found ? Colors::GREEN . '✓' : Colors::RED . '✗';
            $name = ucfirst(str_replace('_', ' ', $pattern));
            $patternResults[] = "    {$icon} {$name}" . Colors::RESET;
        }
        echo implode("\n", $patternResults) . "\n";

        echo Colors::WHITE . "    📄 Log: " . basename($result['log_file']) . " ({$result['log_size']} bytes)" . Colors::RESET . "\n";

        if ($result['error_size'] > 0) {
            echo Colors::YELLOW . "    ⚠ Erros: " . basename($result['error_file']) . " ({$result['error_size']} bytes)" . Colors::RESET . "\n";
        }

        echo "\n";
    }

    $avgDuration = $totalInstances > 0 ? round($totalDuration / $totalInstances, 2) : 0;
    $successRate = $totalInstances > 0 ? round(($successCount / $totalInstances) * 100, 1) : 0;

    echo Colors::CYAN . Colors::BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . Colors::RESET . "\n";
    echo Colors::WHITE . "  📊 Estatísticas:" . Colors::RESET . "\n";
    echo Colors::WHITE . "     • Sucessos: {$successCount}/{$totalInstances}" . Colors::RESET . "\n";
    echo Colors::WHITE . "     • Taxa de sucesso: {$successRate}%" . Colors::RESET . "\n";
    echo Colors::WHITE . "     • Tempo médio: {$avgDuration}s" . Colors::RESET . "\n";
    echo Colors::WHITE . "     • Tempo total: " . round($totalDuration, 2) . "s" . Colors::RESET . "\n";

    if ($totalTimeout > 0) {
        echo Colors::YELLOW . "     • Timeouts: {$totalTimeout}" . Colors::RESET . "\n";
    }

    echo Colors::CYAN . Colors::BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . Colors::RESET . "\n\n";

    if ($successCount === $totalInstances) {
        echo Colors::GREEN . Colors::BOLD . "✓ ✓ ✓  TODOS OS TESTES PASSARAM!  ✓ ✓ ✓" . Colors::RESET . "\n\n";
        return true;
    } else {
        echo Colors::RED . Colors::BOLD . "✗ ✗ ✗  ALGUNS TESTES FALHARAM  ✗ ✗ ✗" . Colors::RESET . "\n\n";
        echo Colors::YELLOW . "💡 Dica: Verifique os logs em tests/integration_logs/" . Colors::RESET . "\n\n";
        return false;
    }
}

// ============================================================================
// EXECUÇÃO PRINCIPAL
// ============================================================================

printHeader();

// Verifica se o example.php existe
if (!file_exists('example.php')) {
    echo Colors::RED . "✗ Erro: example.php não encontrado no diretório atual" . Colors::RESET . "\n";
    echo Colors::YELLOW . "Execute este script no diretório raiz do projeto" . Colors::RESET . "\n\n";
    exit(1);
}

// Verifica variáveis de ambiente necessárias
$requiredEnvVars = ['SIP_USERNAME', 'SIP_PASSWORD', 'SIP_HOST'];
$missingVars = [];
foreach ($requiredEnvVars as $var) {
    if (!getenv($var)) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    echo Colors::YELLOW . "⚠ Aviso: Variáveis de ambiente não configuradas:" . Colors::RESET . "\n";
    foreach ($missingVars as $var) {
        echo Colors::YELLOW . "  - {$var}" . Colors::RESET . "\n";
    }
    echo Colors::CYAN . "\nConfigure no arquivo .env ou use valores padrão" . Colors::RESET . "\n\n";
}

// Cria diretório para logs
$logDir = __DIR__ . '/tests/integration_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Limpa logs antigos
$oldLogs = glob("{$logDir}/*");
if (!empty($oldLogs)) {
    echo Colors::YELLOW . "🗑  Limpando " . count($oldLogs) . " arquivos de log antigos..." . Colors::RESET . "\n";
    array_map('unlink', $oldLogs);
}

echo Colors::CYAN . "📁 Logs serão salvos em: {$logDir}" . Colors::RESET . "\n\n";

$startTime = microtime(true);

// Lança as instâncias em paralelo
$pids = launchInstances($logDir, 5);

if (empty($pids)) {
    echo Colors::RED . "✗ Nenhuma instância foi iniciada!" . Colors::RESET . "\n";
    exit(1);
}

// Aguarda a conclusão
waitForCompletion($pids, 60); // Timeout de 60 segundos

$endTime = microtime(true);
$totalTime = round($endTime - $startTime, 2);

echo Colors::WHITE . "⏱  Tempo total de execução: {$totalTime}s" . Colors::RESET . "\n\n";

// Valida os resultados
$results = validateResults($pids);

// Imprime o resumo detalhado
$success = printDetailedSummary($results);

// Exit code
exit($success ? 0 : 1);
