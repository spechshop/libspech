<?php

declare(strict_types=1);

namespace Swoole\WebSocket;

class Frame implements \Stringable
{
    public $fd = 0;
    public $data = '';
    public $opcode = 1;
    public $flags = 1;
    public $finish = NULL;

    public function __toString(): string {}

    public static function pack(\Swoole\WebSocket\Frame|string $data, int $opcode = 1, int $flags = 1): string {}

    public static function unpack(string $data): \Swoole\WebSocket\Frame {}
}
