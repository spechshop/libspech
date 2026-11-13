<?php

declare(strict_types=1);

namespace Swoole\Coroutine\Http2;

class Client
{
    public $errCode = 0;
    public $errMsg = '';
    public $sock = -1;
    public $type = 0;
    public $setting = NULL;
    public $socket = NULL;
    public $connected = false;
    public $host = NULL;
    public $port = 0;
    public $ssl = false;
    public $serverLastStreamId = 0;

    public function __construct(string $host, int $port = 80, bool $open_ssl = false) {}

    public function __destruct() {}

    public function set(array $settings): bool {}

    public function connect(): bool {}

    public function stats(string $key = ''): array|int {}

    public function isStreamExist(int $stream_id): bool {}

    public function send(\Swoole\Http2\Request $request): int|false {}

    public function write(int $stream_id, mixed $data, bool $end_stream = false): bool {}

    public function recv(float $timeout = 0): \Swoole\Http2\Response|false {}

    public function read(float $timeout = 0): \Swoole\Http2\Response|false {}

    public function goaway(int $error_code = 0, string $debug_data = ''): bool {}

    public function ping(): bool {}

    public function close(): bool {}
}
