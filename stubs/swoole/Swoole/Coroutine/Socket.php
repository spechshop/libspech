<?php

declare(strict_types=1);

namespace Swoole\Coroutine;

class Socket
{
    public readonly mixed $fd;
    public readonly mixed $domain;
    public readonly mixed $type;
    public readonly mixed $protocol;
    public $errCode = 0;
    public $errMsg = '';
    public $__ext_sockets_nonblock = false;
    public $__ext_sockets_timeout = 0;

    public function __construct(int $domain, int $type, int $protocol = 0) {}

    public function bind(string $address, int $port = 0): bool {}

    public function listen(int $backlog = 512): bool {}

    public function accept(float $timeout = 0): \Swoole\Coroutine\Socket|false {}

    public function connect(string $host, int $port = 0, float $timeout = 0): bool {}

    public function checkLiveness(): bool {}

    public function getBoundCid(int $event): int {}

    public function peek(int $length = 65536): string|false {}

    public function recv(int $length = 65536, float $timeout = 0): string|false {}

    public function recvAll(int $length = 65536, float $timeout = 0): string|false {}

    public function recvLine(int $length = 65536, float $timeout = 0): string|false {}

    public function recvWithBuffer(int $length = 65536, float $timeout = 0): string|false {}

    public function recvPacket(float $timeout = 0): string|false {}

    public function send(string $data, float $timeout = 0): int|false {}

    public function readVector(array $io_vector, float $timeout = 0): array|false {}

    public function readVectorAll(array $io_vector, float $timeout = 0): array|false {}

    public function writeVector(array $io_vector, float $timeout = 0): int|false {}

    public function writeVectorAll(array $io_vector, float $timeout = 0): int|false {}

    public function sendFile(string $file, int $offset = 0, int $length = 0): bool {}

    public function sendAll(string $data, float $timeout = 0): int|false {}

    public function recvfrom(mixed &$peername, float $timeout = 0): string|false {}

    public function recvfromInto(\Swoole\Buffer $buffer, mixed &$peername, float $timeout = 0): int|false {}

    public function sendto(string $addr, int $port, string $data): int|false {}

    public function getOption(int $level, int $opt_name): mixed {}

    public function setProtocol(array $settings): bool {}

    public function setOption(int $level, int $opt_name, mixed $opt_value): bool {}

    public function sslHandshake(): bool {}

    public function shutdown(int $how = 2): bool {}

    public function cancel(int $event = 512): bool {}

    public function close(): bool {}

    public function getpeername(): array|false {}

    public function getsockname(): array|false {}

    public function isClosed(): bool {}

    public static function import($stream): \Swoole\Coroutine\Socket|false {}
}
