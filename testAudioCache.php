<?php

ini_set('memory_limit', '512M');

require_once __DIR__ . '/plugins/autoloader.php';

use libspech\Audio\AudioCache;

$pass = 0;
$fail = 0;
$check = function (bool $ok, string $msg) use (&$pass, &$fail) {
    if ($ok) { echo "[OK]   $msg\n"; $pass++; }
    else     { echo "[FAIL] $msg\n"; $fail++; }
};

$file = __DIR__ . '/music.wav';

// 1) Mesma chave p/ mesmos parâmetros
$k1 = AudioCache::makeEncodedKey($file, 'PCMA', 8000, 1, 160);
$k2 = AudioCache::makeEncodedKey($file, 'PCMA', 8000, 1, 160);
$check($k1 === $k2, 'Chaves iguais p/ mesmos parâmetros');

// 2) Codec diferente -> chave diferente
$k3 = AudioCache::makeEncodedKey($file, 'PCMU', 8000, 1, 160);
$check($k1 !== $k3, 'Chave muda quando codec muda');

// 3) Frequência/canais diferentes -> chave diferente
$k4 = AudioCache::makeEncodedKey($file, 'PCMA', 16000, 2, 320);
$check($k1 !== $k4, 'Chave muda quando freq/channels mudam');

// 4) Anti-stampede
$check(AudioCache::markBuilding($k1) === true,  'markBuilding inicial');
$check(AudioCache::markBuilding($k1) === false, 'markBuilding bloqueia segunda tentativa');
$check(AudioCache::isBuilding($k1) === true,    'isBuilding true durante build');
AudioCache::unmarkBuilding($k1);
$check(AudioCache::isBuilding($k1) === false,   'isBuilding false após unmark');

// 5) Set/Get
$payload = [
    'chunks'    => [0 => 'aa', 1 => 'bb'],
    'codec'     => 'PCMA',
    'frequency' => 8000,
    'channels'  => 1,
    'chunkSize' => 160,
];
AudioCache::setEncoded($k1, $payload);
$got = AudioCache::getEncoded($k1);
$check($got !== null && ($got['chunks'][1] ?? null) === 'bb', 'setEncoded/getEncoded preservam payload');
$check(isset($got['lastUsed']) && isset($got['createdAt']),    'setEncoded grava timestamps');

// 6) touchEncoded atualiza lastUsed
$before = $got['lastUsed'];
sleep(1);
AudioCache::touchEncoded($k1);
$after = AudioCache::getEncoded($k1)['lastUsed'];
$check($after >= $before, 'touchEncoded mantém/atualiza lastUsed');

// 7) hasEncoded
$check(AudioCache::hasEncoded($k1) === true, 'hasEncoded true após set');

// 8) cleanup por TTL agressivo remove entradas não-building
AudioCache::cleanup(32, 0);
$check(AudioCache::getEncoded($k1) === null, 'cleanup TTL=0 remove item');

// 9) cleanup não remove building
AudioCache::setEncoded($k2, $payload);
AudioCache::markBuilding($k2);
AudioCache::cleanup(0, 0);
$check(AudioCache::getEncoded($k2) !== null, 'cleanup respeita marca building');
AudioCache::unmarkBuilding($k2);
AudioCache::cleanup(0, 0);

echo "\n--- Resumo: $pass OK, $fail FAIL ---\n";
exit($fail === 0 ? 0 : 1);
