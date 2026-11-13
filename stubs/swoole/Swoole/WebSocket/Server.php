<?php

declare(strict_types=1);

namespace Swoole\WebSocket;

class Server extends \Swoole\Http\Server
{

    public function push(int $fd, \Swoole\WebSocket\Frame|string $data, int $opcode = 1, int $flags = 1): bool {}

    public function disconnect(int $fd, int $code = 1000, string $reason = ''): bool {}

    public function ping(int $fd, string $data = ''): bool {}

    public function isEstablished(int $fd): bool {}

    public static function pack(\Swoole\WebSocket\Frame|string $data, int $opcode = 1, int $flags = 1): string {}

    public static function unpack(string $data): \Swoole\WebSocket\Frame {}
}
