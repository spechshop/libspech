<?php

declare(strict_types=1);

namespace Swoole;

class Client
{
    public const MSG_OOB = 1;
    public const MSG_PEEK = 2;
    public const MSG_DONTWAIT = 64;
    public const MSG_WAITALL = 256;
    public const SHUT_RDWR = 2;
    public const SHUT_RD = 0;
    public const SHUT_WR = 1;
    public $errCode = 0;
    public $sock = -1;
    public $reuse = false;
    public $reuseCount = 0;
    public $type = 0;
    public $id = NULL;
    public $setting = NULL;

    public function __construct(int $type, bool $async = false, string $id = '') {}

    public function __destruct() {}

    public function set(array $settings): bool {}

    public function connect(string $host, int $port = 0, float $timeout = 0.5, int $sock_flag = 0): bool {}

    public function recv(int $size = 65536, int $flag = 0): string|false {}

    public function send(string $data, int $flag = 0): int|false {}

    public function sendfile(string $filename, int $offset = 0, int $length = 0): bool {}

    public function sendto(string $ip, int $port, string $data): bool {}

    public function shutdown(int $how): bool {}

    public function enableSSL(?callable $onSslReady = NULL): bool {}

    public function getPeerCert(): string|bool {}

    public function verifyPeerCert(): bool {}

    public function isConnected(): bool {}

    public function getsockname(): array|false {}

    public function getpeername(): array|false {}

    public function close(bool $force = false): bool {}

    public function getSocket(): \Socket|false {}
}
