<?php

/**
 * Runner de testes para rtpChannel
 * Executa toda a suite de testes e exibe resultados
 */

require_once __DIR__ . '/tests/RtpChannelTest.php';

use Tests\RtpChannelTest;

// Limpa a tela
system('clear');

echo "\n";
echo "\033[1;35m╔═══════════════════════════════════════════╗\033[0m\n";
echo "\033[1;35m║                                           ║\033[0m\n";
echo "\033[1;35m║         RTP Channel Test Suite           ║\033[0m\n";
echo "\033[1;35m║                                           ║\033[0m\n";
echo "\033[1;35m║  Validação completa de rtpChannel.php     ║\033[0m\n";
echo "\033[1;35m║  Conformidade RFC 3550 e RFC 2833         ║\033[0m\n";
echo "\033[1;35m║                                           ║\033[0m\n";
echo "\033[1;35m╚═══════════════════════════════════════════╝\033[0m\n";

$startTime = microtime(true);

// Executa os testes
$testSuite = new RtpChannelTest();
$success = $testSuite->runAll();

$endTime = microtime(true);
$duration = round($endTime - $startTime, 3);

echo "\n";
echo "\033[1;36m⏱  Tempo de execução: {$duration}s\033[0m\n";
echo "\n";

// Exit code
exit($success ? 0 : 1);
