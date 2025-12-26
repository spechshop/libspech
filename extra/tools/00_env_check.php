<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "SAPI: " . PHP_SAPI . PHP_EOL;
echo "OS: " . PHP_OS_FAMILY . PHP_EOL;

$extensions = [
    'swoole',
    'openssl',
];

echo PHP_EOL . "Extensions:" . PHP_EOL;
foreach ($extensions as $ext) {
    echo "- {$ext}: " . (extension_loaded($ext) ? 'yes' : 'no') . PHP_EOL;
}

extra_bootstrap();

echo PHP_EOL . "Functions/classes (from libspech loader):" . PHP_EOL;
$checks = [
    'function resampler' => function_exists('resampler'),
    'function encodePcmToPcma' => function_exists('encodePcmToPcma'),
    'function decodePcmaToPcm' => function_exists('decodePcmaToPcm'),
    'function encodePcmToPcmu' => function_exists('encodePcmToPcmu'),
    'function decodePcmuToPcm' => function_exists('decodePcmuToPcm'),
    'function encodePcmToL16' => function_exists('encodePcmToL16'),
    'function decodeL16ToPcm' => function_exists('decodeL16ToPcm'),
    'function mixAudioChannels' => function_exists('mixAudioChannels'),
    'class opusChannel' => class_exists('opusChannel'),
    'function libspech\\Sip\\waveHead3' => function_exists('\\libspech\\Sip\\waveHead3'),
];

foreach ($checks as $label => $ok) {
    echo "- {$label}: " . ($ok ? 'yes' : 'no') . PHP_EOL;
}

