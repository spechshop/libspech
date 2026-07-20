<?php

declare(strict_types=1);

namespace Swoole\Coroutine\Server;


class Connection {

    
    public function __construct(\Swoole\Coroutine\Socket $conn) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recv(\float $timeout = 0.0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function send(\string $data) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exportSocket(): \Swoole\Coroutine\Socket {
        return class_exists(\Swoole\Coroutine\Socket::class) ? \Swoole\Coroutine\Socket::class : \stdClass::class;
    }
}
