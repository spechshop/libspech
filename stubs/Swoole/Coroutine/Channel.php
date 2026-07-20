<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class Channel {

    
    public function __construct(\int $size = 1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function push($data, \float $timeout = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function pop(\float $timeout = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isEmpty(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isFull(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function stats(): \array {
        return [];
    }

    
    public function length(): \int {
        return 0;
    }
}
