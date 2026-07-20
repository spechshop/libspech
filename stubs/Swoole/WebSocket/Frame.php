<?php

declare(strict_types=1);

namespace Swoole\WebSocket;


class Frame {

    
    public function __toString(): \string {
        return "";
    }

    
    public static function pack(\Swoole\WebSocket\Frame|string $data, \int $opcode = 1, \int $flags = 1): \string {
        return "";
    }

    
    public static function unpack(\string $data): \Swoole\WebSocket\Frame {
        return class_exists(\Swoole\WebSocket\Frame::class) ? \Swoole\WebSocket\Frame::class : \stdClass::class;
    }
}
