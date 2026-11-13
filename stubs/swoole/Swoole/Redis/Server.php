<?php

declare(strict_types=1);

namespace Swoole\Redis;

class Server extends \Swoole\Server
{
    public const NIL = 1;
    public const ERROR = 0;
    public const STATUS = 2;
    public const INT = 3;
    public const STRING = 4;
    public const SET = 5;
    public const MAP = 6;

    public function setHandler(string $command, callable $callback): bool {}

    public function getHandler(string $command): \Closure {}

    public static function format(int $type, mixed $value = NULL): string|false {}
}
