<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

extra_bootstrap();

$outDir = extra_root() . '/extra/stubs';
if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    throw new RuntimeException("Failed to create stubs dir: {$outDir}");
}

$target = $outDir . '/extra_audio_stubs.php';

$lines = [];
$lines[] = "<?php";
$lines[] = "";
$lines[] = "declare(strict_types=1);";
$lines[] = "";
$lines[] = "/**";
$lines[] = " * AUTO-GENERATED stubs for `extra/*` examples.";
$lines[] = " * Run: php extra/tools/01_generate_stubs.php";
$lines[] = " */";
$lines[] = "";

$functions = [
    ['decodePcmaToPcm', 'string', ['string $input']],
    ['decodePcmuToPcm', 'string', ['string $input']],
    ['encodePcmToPcma', 'string', ['string $input']],
    ['encodePcmToPcmu', 'string', ['string $input']],
    ['decodeL16ToPcm', 'string', ['string $input']],
    ['encodePcmToL16', 'string', ['string $input']],
    ['mixAudioChannels', 'string', ['array $channels', 'int $sample_rate']],
    ['resampler', 'string', ['string $pcm', 'int $src_rate', 'int $dst_rate', 'bool $stereo = false']],
];

foreach ($functions as [$name, $returnType, $args]) {
    if (!function_exists($name)) {
        continue;
    }
    $argList = implode(', ', $args);
    $lines[] = "function {$name}({$argList}): {$returnType} {}";
    $lines[] = "";
}

if (class_exists('opusChannel')) {
    $lines[] = "class opusChannel";
    $lines[] = "{";
    $lines[] = "    public function __construct(int \$sample_rate, int \$channels) {}";
    $lines[] = "    public function encode(string \$pcm_data, int \$pcm_rate): string {}";
    $lines[] = "    public function decode(string \$encoded_data, int \$pcm_rate_out): string {}";
    $lines[] = "    public function resample(string \$pcm_data, int \$src_rate, int \$dst_rate): string {}";
    $lines[] = "    public function setBitrate(int \$value) {}";
    $lines[] = "    public function setVBR(bool \$enable) {}";
    $lines[] = "    public function setComplexity(int \$value) {}";
    $lines[] = "    public function setDTX(bool \$enable) {}";
    $lines[] = "    public function setSignalVoice(bool \$enable) {}";
    $lines[] = "    public function reset() {}";
    $lines[] = "    public function enhanceVoiceClarity(string \$pcm_data, float \$intensity): string {}";
    $lines[] = "    public function spatialStereoEnhance(string \$pcm_data, float \$width, float \$depth): string {}";
    $lines[] = "    public function destroy() {}";
    $lines[] = "}";
    $lines[] = "";
}

file_put_contents($target, implode(PHP_EOL, $lines));
echo "Wrote: {$target}" . PHP_EOL;

