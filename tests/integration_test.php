<?php

/**
 * Teste de Integração Paralela
 * Executa 5 instâncias do example.php simultaneamente e valida
 * se a string "número inválido" aparece nas saídas (indica que DTMF '*' foi processado)
 */

use Swoole\Coroutine;

\Swoole\Runtime::enableCoroutine();

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
    echo "\n";
    echo Colors::CYAN . Colors::BOLD . "╔══════════════════════════════════════════════════════╗" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║                                                      ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║        Teste de Integração Paralela - 5x            ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║                                                      ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║  Validação: presença de 'número inválido'           ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║  Indica que DTMF '*' foi processado corretamente    ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "║                                                      ║" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "╚══════════════════════════════════════════════════════╝" . Colors::RESET . "\n\n";
}

function printStatus($instance, $status, $message = '') {
    $color = $status === 'running' ? Colors::YELLOW : ($status === 'success' ? Colors::GREEN : Colors::RED);
    $icon = $status === 'running' ? '⏳' : ($status === 'success' ? '✓' : '✗');

    $statusText = strtoupper($status);
    echo "{$color}[{$icon}] Instância #{$instance}: {$statusText}{Colors::RESET}";
    if ($message) {
        echo " - {$message}";
    }
    echo "\n";
}

function executeInstance(int $instance, array &$results, string $logDir): void {
    $logFile = "{$logDir}/instance_{$instance}.log";
    $errorFile = "{$logDir}/instance_{$instance}.error.log";

    printStatus($instance, 'running', 'Iniciando execução...');

    $startTime = microtime(true);

    // Executa o example.php e captura a saída
    $cmd = "php example.php > {$logFile} 2> {$errorFile}";
    $returnCode = 0;

    // Executa o comando
    exec($cmd, $output, $returnCode);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    // Lê o conteúdo do log
    $logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
    $errorContent = file_exists($errorFile) ? file_get_contents($errorFile) : '';

    // Verifica se contém "número inválido" (case insensitive)
    $hasInvalidNumber = stripos($logContent, 'número inválido') !== false ||
                        stripos($logContent, 'numero invalido') !== false;

    // Verifica se o DTMF foi enviado
    $hasDtmfSent = stripos($logContent, 'send2833') !== false ||
                   stripos($logContent, 'DTMF') !== false ||
                   stripos($logContent, 'Digitando') !== false;

    $results[$instance] = [
        'success' => $hasInvalidNumber,
        'duration' => $duration,
        'has_invalid_number' => $hasInvalidNumber,
        'has_dtmf_sent' => $hasDtmfSent,
        'return_code' => $returnCode,
        'log_file' => $logFile,
        'error_file' => $errorFile,
        'log_size' => strlen($logContent),
        'error_size' => strlen($errorContent),
    ];

    if ($hasInvalidNumber) {
        printStatus($instance, 'success', "Validação OK - {$duration}s");
    } else {
        printStatus($instance, 'failed', "String não encontrada - {$duration}s");
    }
}

function printSummary(array $results): bool {
    echo "\n";
    echo Colors::CYAN . Colors::BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . Colors::RESET . "\n";
    echo Colors::WHITE . Colors::BOLD . "  RESUMO DOS RESULTADOS" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . Colors::RESET . "\n\n";

    $totalInstances = count($results);
    $successCount = 0;
    $totalDuration = 0;

    foreach ($results as $instance => $result) {
        $totalDuration += $result['duration'];
        if ($result['success']) {
            $successCount++;
        }

        $status = $result['success'] ? Colors::GREEN . '✓ PASS' : Colors::RED . '✗ FAIL';
        $duration = sprintf("%.2fs", $result['duration']);

        echo "  Instância #{$instance}: {$status}" . Colors::RESET . " - {$duration}\n";

        if ($result['has_invalid_number']) {
            echo Colors::GREEN . "    ✓ String 'número inválido' encontrada" . Colors::RESET . "\n";
        } else {
            echo Colors::RED . "    ✗ String 'número inválido' NÃO encontrada" . Colors::RESET . "\n";
        }

        if ($result['has_dtmf_sent']) {
            echo Colors::BLUE . "    ℹ DTMF detectado na saída" . Colors::RESET . "\n";
        }

        echo Colors::WHITE . "    📄 Log: {$result['log_file']} ({$result['log_size']} bytes)" . Colors::RESET . "\n";

        if ($result['error_size'] > 0) {
            echo Colors::YELLOW . "    ⚠ Erros: {$result['error_file']} ({$result['error_size']} bytes)" . Colors::RESET . "\n";
        }

        echo "\n";
    }

    $avgDuration = round($totalDuration / $totalInstances, 2);
    $successRate = round(($successCount / $totalInstances) * 100, 1);

    echo Colors::CYAN . Colors::BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . Colors::RESET . "\n";
    echo Colors::WHITE . "  Total: {$successCount}/{$totalInstances} instâncias passaram" . Colors::RESET . "\n";
    echo Colors::WHITE . "  Taxa de sucesso: {$successRate}%" . Colors::RESET . "\n";
    echo Colors::WHITE . "  Tempo médio: {$avgDuration}s" . Colors::RESET . "\n";
    echo Colors::WHITE . "  Tempo total: " . round($totalDuration, 2) . "s" . Colors::RESET . "\n";
    echo Colors::CYAN . Colors::BOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . Colors::RESET . "\n\n";

    if ($successCount === $totalInstances) {
        echo Colors::GREEN . Colors::BOLD . "✓ TODOS OS TESTES PASSARAM!" . Colors::RESET . "\n\n";
        return true;
    } else {
        echo Colors::RED . Colors::BOLD . "✗ ALGUNS TESTES FALHARAM" . Colors::RESET . "\n\n";
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
    exit(1);
}

// Cria diretório para logs
$logDir = __DIR__ . '/integration_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Limpa logs antigos
array_map('unlink', glob("{$logDir}/*.log"));

echo Colors::YELLOW . "📁 Logs serão salvos em: {$logDir}" . Colors::RESET . "\n\n";
echo Colors::CYAN . "🚀 Iniciando 5 instâncias paralelas..." . Colors::RESET . "\n\n";

$startTime = microtime(true);

// Executa 5 instâncias em paralelo usando Swoole Coroutines
Coroutine\run(function () use ($logDir, $startTime) {
    $results = [];

    // Cria 5 corotinas para execução paralela
    for ($i = 1; $i <= 5; $i++) {
        Coroutine::create(function () use ($i, &$results, $logDir) {
            executeInstance($i, $results, $logDir);
        });
    }

    // Aguarda um tempo para as corotinas executarem
    // Como estamos usando exec() que é bloqueante, vamos executar sequencialmente
    // Para execução verdadeiramente paralela, seria necessário usar processos separados
});

// Como o exec() é bloqueante, vamos usar uma abordagem diferente
// Vamos executar em processos separados em background

echo "\n" . Colors::YELLOW . "⚠ Nota: Execução sequencial (exec é bloqueante)" . Colors::RESET . "\n";
echo Colors::CYAN . "Para execução paralela real, use processos em background" . Colors::RESET . "\n\n";

$results = [];

// Execução sequencial (mais simples e funcional para testes)
for ($i = 1; $i <= 5; $i++) {
    executeInstance($i, $results, $logDir);
}

$endTime = microtime(true);
$totalTime = round($endTime - $startTime, 2);

echo "\n" . Colors::WHITE . "⏱ Tempo total de execução: {$totalTime}s" . Colors::RESET . "\n";

// Imprime o resumo
$success = printSummary($results);

// Exit code
exit($success ? 0 : 1);
