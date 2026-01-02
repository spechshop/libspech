<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

if (!extension_loaded('swoole')) {
    fwrite(STDERR, "Missing extension: swoole\n");
    exit(1);
}
if (!class_exists('opusChannel')) {
    fwrite(STDERR, "Missing class: opusChannel\n");
    exit(1);
}

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

$asset = extra_root() . '/extra/assets/music.wav';
$wav = extra_read_wav_pcm16le($asset);

$pcm = $wav['pcm'];
$srcRate = $wav['sampleRate'];
$channels = $wav['channels'];

if ($channels === 2) {
    // Convert to mono before adding spatial FX to keep consistent width/depth behavior.
    $pcm = extra_downmix_to_mono($pcm);
    $channels = 1;
}

// Work in 48 kHz mono for spatial FX, then encode to Opus stereo.
$pcm48 = extra_resample_pcm16le($pcm, $srcRate, 48000);

$server = new Server('127.0.0.1', 9502);
$server->set([
    'worker_num' => 1,
    'enable_coroutine' => true,
    'log_level' => SWOOLE_LOG_WARNING,
]);

$server->on('request', function (Request $req, Response $res) use ($pcm48) {
    $uri = $req->server['request_uri'] ?? '/';
    if ($uri === '/health') {
        $res->header('Content-Type', 'text/plain; charset=utf-8');
        $res->end("ok\n");
        return;
    }

    if ($uri !== '/opus') {
        $res->status(404);
        $res->header('Content-Type', 'text/plain; charset=utf-8');
        $res->end("Use /opus to stream raw Opus frames (48k stereo). Try: curl http://127.0.0.1:9502/opus | ffplay -f opus -ar 48000 -ac 2 -");
        return;
    }

    $width = isset($req->get['width']) ? (float)$req->get['width'] : 1.5;
    $depth = isset($req->get['depth']) ? (float)$req->get['depth'] : 0.65;

    $fx = new opusChannel(48000, 1);
    $enc = new opusChannel(48000, 2);

    $res->header('Content-Type', 'audio/opus'); // raw Opus frames (no Ogg container)
    $res->header('Cache-Control', 'no-store');

    $frameBytes = 1920; // 20ms @48k mono
    $len = strlen($pcm48);
    for ($offset = 0; $offset < $len; $offset += $frameBytes) {
        $frame = substr($pcm48, $offset, $frameBytes);
        if ($frame === '') {
            continue;
        }

        $stereo = $fx->spatialStereoEnhance($frame, $width, $depth); // returns 2ch PCM16LE
        $packet = $enc->encode($stereo, 48000); // Opus-encoded frame
        $res->write($packet);

        // Pace ~20ms to mimic realtime and avoid buffering everything at once.
        \Swoole\Coroutine::sleep(0.02);
    }

    $fx->destroy();
    $enc->destroy();
    $res->end();
});

echo "Listening on http://127.0.0.1:9502\n";
echo "Stream raw Opus stereo (spatial): curl http://127.0.0.1:9502/opus | ffplay -f opus -ar 48000 -ac 2 -\n";
$server->start();

