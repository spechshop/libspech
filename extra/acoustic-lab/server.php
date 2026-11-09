<?php

declare(strict_types=1);

use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

if (!extension_loaded('swoole') || !class_exists('AcousticStreamAnalyzer')) {
    fwrite(STDERR, "Requer Swoole e psampler com AcousticStreamAnalyzer.\n");
    exit(1);
}

const LAB_MAX_WAV_BYTES = 32 * 1024 * 1024;
const LAB_FRAME_BYTES = 320; // PCM16LE mono, 8 kHz, 20 ms
const LAB_FRAME_SECONDS = 0.02;
const LAB_EVENTS = [
    'ring.started', 'ring.ended', 'ring.cadence_detected',
    'ring.interrupted', 'speech.started', 'speech.ended', 'signal.changed',
];

$root = dirname(__DIR__, 2); // libspech / extra / acoustic-lab
$audioDir = __DIR__ . '/audio';
if (!is_dir($audioDir) && !mkdir($audioDir, 0700, true) && !is_dir($audioDir)) {
    throw new RuntimeException('Nao foi possivel criar diretorio de audio');
}

/** @return array<string,string> */
function labFiles(string $root, string $audioDir): array
{
    $files = [];
    foreach (['root' => $root, 'lab' => $audioDir] as $prefix => $directory) {
        foreach (glob($directory . '/*') ?: [] as $path) {
            if (!is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'wav') {
                continue;
            }
            if (filesize($path) > LAB_MAX_WAV_BYTES) {
                continue;
            }
            $name = basename($path);
            if (!preg_match('/^[a-zA-Z0-9_ .()\-]+\.wav$/iD', $name)) {
                continue;
            }
            $files[$prefix . ':' . $name] = ($prefix === 'root' ? 'libspech / ' : 'Laboratorio / ') . $name;
        }
    }
    ksort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

function labResolve(string $id, string $root, string $audioDir): ?string
{
    if (!preg_match('/^(root|lab):([a-zA-Z0-9_ .()\-]+\.wav)$/iD', $id, $m)) {
        return null;
    }
    $dir = $m[1] === 'root' ? $root : $audioDir;
    $path = realpath($dir . '/' . $m[2]);
    $realDir = realpath($dir);
    if ($path === false || $realDir === false || dirname($path) !== $realDir || !is_file($path)) {
        return null;
    }
    $size = filesize($path);
    return $size !== false && $size <= LAB_MAX_WAV_BYTES ? $path : null;
}

/** @return array{pcm: string, rate: int, channels: int, duration_ms: int} */
function labReadWav(string $path): array
{
    $data = file_get_contents($path);
    if ($data === false || strlen($data) < 44 || substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WAVE') {
        throw new RuntimeException('Arquivo WAV RIFF invalido');
    }
    $fmt = null;
    $pcm = null;
    $offset = 12;
    $size = strlen($data);
    while ($offset + 8 <= $size) {
        $chunkId = substr($data, $offset, 4);
        $chunkSize = unpack('V', substr($data, $offset + 4, 4))[1];
        $offset += 8;
        if ($chunkSize > $size - $offset) {
            throw new RuntimeException('WAV truncado');
        }
        if ($chunkId === 'fmt ' && $chunkSize >= 16) {
            $fmt = unpack('vformat/vchannels/Vrate/VbyteRate/vblockAlign/vbits', substr($data, $offset, 16));
        } elseif ($chunkId === 'data' && $pcm === null) {
            $pcm = substr($data, $offset, $chunkSize);
        }
        $offset += $chunkSize + ($chunkSize % 2);
    }
    if (!$fmt || $pcm === null || (int)$fmt['format'] !== 1 || (int)$fmt['bits'] !== 16) {
        throw new RuntimeException('Aceita apenas WAV PCM16 linear (nao MP3/Opus/float)');
    }
    $rate = (int)$fmt['rate'];
    $channels = (int)$fmt['channels'];
    if (!in_array($channels, [1, 2], true) || $rate < 8000 || $rate > 192000 || (int)$fmt['blockAlign'] !== 2 * $channels || strlen($pcm) % (2 * $channels) !== 0) {
        throw new RuntimeException('WAV com canais, frequencia ou alinhamento invalidos');
    }
    $durationMs = (int)round((strlen($pcm) / (2 * $channels) / $rate) * 1000);
    if ($durationMs <= 0 || $durationMs > 15 * 60 * 1000) {
        throw new RuntimeException('Duracao permitida: ate 15 minutos');
    }
    return ['pcm' => $pcm, 'rate' => $rate, 'channels' => $channels, 'duration_ms' => $durationMs];
}

function labPreparePcm(string $path): array
{
    $wav = labReadWav($path);
    $pcm = $wav['pcm'];
    if ($wav['channels'] === 2) {
        $pcm = stereoToMono($pcm);
    }
    if ($wav['rate'] !== 8000) {
        $pcm = (new Resampler($wav['rate'], 8000))->process($pcm);
    }
    $wav['pcm'] = $pcm;
    return $wav;
}

function labJson(Response $res, array $data, int $status = 200): void
{
    $res->status($status);
    $res->header('Content-Type', 'application/json; charset=utf-8');
    $res->header('Cache-Control', 'no-store');
    $res->end(json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function labSse(Response $res, string $name, array $data): bool
{
    return $res->write('event: ' . $name . "\n" . 'data: ' . json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n\n");
}

$server = new Server('127.0.0.1', 9510);
$server->set([
    'worker_num' => 1,
    'enable_coroutine' => true,
    'log_level' => SWOOLE_LOG_WARNING,
    'package_max_length' => LAB_MAX_WAV_BYTES + 1048576,
]);

$server->on('request', static function (Request $req, Response $res) use ($root, $audioDir): void {
    $path = $req->server['request_uri'] ?? '/';
    $method = strtoupper($req->server['request_method'] ?? 'GET');

    try {
        if ($path === '/' || $path === '/app.js' || $path === '/style.css') {
            if ($method !== 'GET') {
                labJson($res, ['error' => 'Metodo nao permitido'], 405);
                return;
            }
            $file = match ($path) {
                '/' => __DIR__ . '/public/index.html',
                '/app.js' => __DIR__ . '/public/app.js',
                '/style.css' => __DIR__ . '/public/style.css',
            };
            $res->header('Content-Type', match ($path) {
                '/' => 'text/html; charset=utf-8',
                '/app.js' => 'text/javascript; charset=utf-8',
                '/style.css' => 'text/css; charset=utf-8',
            });
            $res->header('Cache-Control', 'no-store');
            $res->sendfile($file);
            return;
        }

        if ($path === '/api/files' && $method === 'GET') {
            $files = labFiles($root, $audioDir);
            labJson($res, ['files' => array_map(static fn($id, $name) => ['id' => $id, 'name' => $name], array_keys($files), $files)]);
            return;
        }

        if ($path === '/api/upload' && $method === 'POST') {
            $upload = $req->files['audio'] ?? null;
            if (!is_array($upload) || ($upload['error'] ?? 1) !== UPLOAD_ERR_OK || !isset($upload['tmp_name'], $upload['size'], $upload['name'])) {
                labJson($res, ['error' => 'Falha ao receber o arquivo'], 400);
                return;
            }
            if ((int)$upload['size'] > LAB_MAX_WAV_BYTES || (int)$upload['size'] < 44 || strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION)) !== 'wav') {
                labJson($res, ['error' => 'Envie WAV com tamanho ate 32 MiB'], 400);
                return;
            }
            $safeName = substr((string)preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename((string)$upload['name'])), 0, 150);
            $destName = bin2hex(random_bytes(6)) . '-' . $safeName;
            $destPath = $audioDir . '/' . $destName;
            $tmpPath = (string)$upload['tmp_name'];
            if (!move_uploaded_file($tmpPath, $destPath)) {
                labJson($res, ['error' => 'Nao foi possivel salvar o upload'], 500);
                return;
            }
            try {
                labReadWav($destPath);
            } catch (Throwable $e) {
                unlink($destPath);
                throw $e;
            }
            labJson($res, ['id' => 'lab:' . $destName, 'name' => 'Laboratorio / ' . $destName]);
            return;
        }

        if (($path === '/api/audio' || $path === '/api/stream') && $method === 'GET') {
            $file = labResolve((string)($req->get['id'] ?? ''), $root, $audioDir);
            if ($file === null) {
                labJson($res, ['error' => 'Audio nao encontrado'], 404);
                return;
            }
            if ($path === '/api/audio') {
                $res->header('Content-Type', 'audio/wav');
                $res->header('Cache-Control', 'no-store');
                $res->sendfile($file);
                return;
            }

            // Analise incremental em um fluxo SSE. Cada conexao tem uma instancia propria.
            $prepared = labPreparePcm($file);
            $pcm = $prepared['pcm'];
            $analyzer = new AcousticStreamAnalyzer(8000, 20);
            $events = [];
            foreach (LAB_EVENTS as $type) {
                $analyzer->on($type, static function (array $event) use (&$events): void {
                    $events[] = $event;
                });
            }

            $res->header('Content-Type', 'text/event-stream; charset=utf-8');
            $res->header('Cache-Control', 'no-cache, no-transform');
            $res->header('Connection', 'keep-alive');
            $res->header('X-Accel-Buffering', 'no');
            if (!labSse($res, 'ready', [
                'filename' => basename($file),
                'duration_ms' => $prepared['duration_ms'],
                'sample_rate' => $prepared['rate'],
                'channels' => $prepared['channels'],
                'state' => $analyzer->state(),
            ])) {
                $res->end();
                return;
            }

            $nextAt = microtime(true);
            $length = strlen($pcm);
            $frameCounter = 0;
            for ($offset = 0; $offset < $length; $offset += LAB_FRAME_BYTES) {
                $analyzer->push(substr($pcm, $offset, LAB_FRAME_BYTES));
                foreach ($events as $event) {
                    if (!labSse($res, 'acoustic', $event)) {
                        $res->end();
                        return;
                    }
                }
                $events = [];
                $frameCounter++;
                if ($frameCounter % 5 === 0 || $offset + LAB_FRAME_BYTES >= $length) {
                    if (!labSse($res, 'status', $analyzer->state())) {
                        $res->end();
                        return;
                    }
                }
                $nextAt += LAB_FRAME_SECONDS;
                $delay = $nextAt - microtime(true);
                if ($delay > 0) {
                    Coroutine::sleep($delay);
                }
            }
            labSse($res, 'done', ['state' => $analyzer->state()]);
            $res->end();
            return;
        }

        labJson($res, ['error' => 'Rota nao encontrada'], 404);
    } catch (Throwable $e) {
        // O streaming pode ja ter enviado cabecalhos; nesse caso apenas encerra.
        if ($path === '/api/stream') {
            try {
                labSse($res, 'failure', ['error' => $e->getMessage()]);
            } catch (Throwable) {
            }
            $res->end();
        } else {
            labJson($res, ['error' => $e->getMessage()], 400);
        }
    }
});

echo "Acoustic Lab: http://127.0.0.1:9510\n";
echo "PHP " . PHP_VERSION . ' | psampler ' . (phpversion('psampler') ?: '?') . "\n";
$server->start();
