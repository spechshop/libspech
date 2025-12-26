<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

if (!extension_loaded('swoole')) {
    fwrite(STDERR, "Missing extension: swoole\n");
    exit(1);
}

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

$asset = extra_root() . '/extra/assets/music.wav';
$wav = extra_read_wav_pcm16le($asset);

$srcPcm = $wav['pcm'];
$srcRate = $wav['sampleRate'];
$srcChannels = $wav['channels'];

if ($srcChannels === 2) {
    $srcPcm = extra_downmix_to_mono($srcPcm);
    $srcChannels = 1;
}

$server = new Server('127.0.0.1', 9501);
$server->set([
    'worker_num' => 1,
    'enable_coroutine' => true,
    'log_level' => SWOOLE_LOG_WARNING,
]);

$server->on('request', function (Request $request, Response $response) use ($srcPcm, $srcRate, $srcChannels): void {
    $uri = $request->server['request_uri'] ?? '/';
    if ($uri === '/health') {
        $response->header('Content-Type', 'text/plain; charset=utf-8');
        $response->end("ok\n");
        return;
    }

    if ($uri !== '/music.wav') {
        $response->status(404);
        $response->header('Content-Type', 'text/plain; charset=utf-8');
        $response->end("Use /music.wav?rate=8000|16000|48000\n");
        return;
    }

    $rate = (int)($request->get['rate'] ?? 8000);
    if (!in_array($rate, [8000, 16000, 24000, 48000], true)) {
        $rate = 8000;
    }

    $pcm = extra_resample_pcm16le($srcPcm, $srcRate, $rate);
    $header = \libspech\Sip\waveHead3(strlen($pcm), $rate, $srcChannels);

    $response->header('Content-Type', 'audio/wav');
    $response->header('Cache-Control', 'no-store');
    $response->write($header);

    $chunkBytes = 4096;
    $chunks = str_split($pcm, $chunkBytes);
    foreach ($chunks as $chunk) {
        $response->write($chunk);
        \Swoole\Coroutine::sleep(0.02);
    }

    $response->end();
});

echo "Listening on http://127.0.0.1:9501\n";
echo "Try: curl -o /tmp/music.wav 'http://127.0.0.1:9501/music.wav?rate=8000'\n";
$server->start();

