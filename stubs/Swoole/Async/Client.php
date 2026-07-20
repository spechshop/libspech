<?php

declare(strict_types=1);

namespace Swoole\Async;


class Client {
    public const MSG_OOB = 1;
    public const MSG_PEEK = 2;
    public const MSG_DONTWAIT = 64;
    public const MSG_WAITALL = 256;
    public const SHUT_RDWR = 2;
    public const SHUT_RD = 0;
    public const SHUT_WR = 1;

    
    public function __construct(\int $type) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function connect(\string $host, \int $port = 0, \float $timeout = 0.5, \int $sock_flag = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sleep(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function wakeup(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function pause(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function resume(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function enableSSL(\callable $onSslReady = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isConnected(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(\bool $force = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function on(\string $host, \callable $callback): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
