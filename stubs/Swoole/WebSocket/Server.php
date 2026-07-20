<?php

declare(strict_types=1);

namespace Swoole\WebSocket;


class Server {

    
    public function push(\int $fd, \Swoole\WebSocket\Frame|string $data, \int $opcode = 1, \int $flags = 1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function disconnect(\int $fd, \int $code = 1000, \string $reason = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function ping(\int $fd, \string $data = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isEstablished(\int $fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function pack(\Swoole\WebSocket\Frame|string $data, \int $opcode = 1, \int $flags = 1): \string {
        return "";
    }

    
    public static function unpack(\string $data): \Swoole\WebSocket\Frame {
        return class_exists(\Swoole\WebSocket\Frame::class) ? \Swoole\WebSocket\Frame::class : \stdClass::class;
    }
}
