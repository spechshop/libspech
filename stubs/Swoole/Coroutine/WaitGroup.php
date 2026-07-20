<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class WaitGroup {

    
    public function __construct(\int $delta = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function add(\int $delta = 1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function done() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function wait(\float $timeout = -1.0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }
}
