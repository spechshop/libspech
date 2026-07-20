<?php

declare(strict_types=1);

namespace Swoole;


class Lock {
    public const MUTEX = 3;
    public const RWLOCK = 1;
    public const SPINLOCK = 5;

    
    public function __construct(\int $type = 3) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function lock(\int $operation = 2, \float $timeout = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function unlock(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
