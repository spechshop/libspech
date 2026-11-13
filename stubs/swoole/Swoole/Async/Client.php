<?php

declare(strict_types=1);

namespace Swoole\Async;

class Client extends \Swoole\Client
{
    private $onConnect = NULL;
    private $onError = NULL;
    private $onReceive = NULL;
    private $onClose = NULL;
    private $onBufferFull = NULL;
    private $onBufferEmpty = NULL;
    private $onSSLReady = NULL;

    public function __construct(int $type) {}

    public function __destruct() {}

    public function connect(string $host, int $port = 0, float $timeout = 0.5, int $sock_flag = 0): bool {}

    public function sleep(): bool {}

    public function wakeup(): bool {}

    public function pause(): bool {}

    public function resume(): bool {}

    public function enableSSL(?callable $onSslReady = NULL): bool {}

    public function isConnected(): bool {}

    public function close(bool $force = false): bool {}

    public function on(string $host, callable $callback): bool {}
}
