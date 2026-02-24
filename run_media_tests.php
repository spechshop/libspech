<?php

/**
 * Runner de testes para MediaChannel
 */

require_once __DIR__ . '/tests/MediaChannelTest.php';

use Tests\MediaChannelTest;

// Limpa a tela
system('clear');

echo "\n";
echo "\033[1;35m╔═══════════════════════════════════════════╗\033[0m\n";
echo "\033[1;35m║                                           ║\033[0m\n";
echo "\033[1;35m║       MediaChannel Test Suite            ║\033[0m\n";
echo "\033[1;35m║                                           ║\033[0m\n";
echo "\033[1;35m║  Validação de mediaChannel.php            ║\033[0m\n";
echo "\033[1;35m║  Codecs, DTMF, VAD e Members              ║\033[0m\n";
echo "\033[1;35m║                                           ║\033[0m\n";
echo "\033[1;35m╚═══════════════════════════════════════════╝\033[0m\n";

$startTime = microtime(true);

// Executa os testes
$testSuite = new MediaChannelTest();
$success = $testSuite->runAll();

$endTime = microtime(true);
$duration = round($endTime - $startTime, 3);

echo "\n";
echo "\033[1;36m⏱  Tempo de execução: {$duration}s\033[0m\n";
echo "\n";

// Exit code
exit($success ? 0 : 1);
