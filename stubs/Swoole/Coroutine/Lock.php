<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class Lock {

    
    public function __construct(\bool $shared = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function lock(\int $operation = 2): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function unlock(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
