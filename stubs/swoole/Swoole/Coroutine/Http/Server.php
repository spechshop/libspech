<?php

declare(strict_types=1);

namespace Swoole\Coroutine\Http;

final class Server
{
    public $fd = -1;
    public $host = NULL;
    public $port = -1;
    public $ssl = false;
    public $settings = NULL;
    public $errCode = 0;
    public $errMsg = '';

    public function __construct(string $host, int $port = 0, bool $ssl = false, bool $reuse_port = false) {}

    public function set(array $settings): bool {}

    public function handle(string $pattern, callable $callback): bool {}

    private function onAccept(\Swoole\Coroutine\Socket $conn): void {}

    public function start(): bool {}

    public function shutdown(): void {}
}
