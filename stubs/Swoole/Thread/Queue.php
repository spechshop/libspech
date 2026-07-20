<?php

declare(strict_types=1);

namespace Swoole\Thread;


class Queue {
    public const NOTIFY_ONE = 1;
    public const NOTIFY_ALL = 2;

    
    public function __construct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function push($value, \int $notify_which = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function pop(\float $wait = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function clean() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }
}
