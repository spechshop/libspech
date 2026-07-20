<?php

declare(strict_types=1);

namespace Swoole;


class Atomic {

    
    public function __construct(\int $value = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function add(\int $add_value = 1): \int {
        return 0;
    }

    
    public function sub(\int $sub_value = 1): \int {
        return 0;
    }

    
    public function get(): \int {
        return 0;
    }

    
    public function set(\int $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function wait(\float $timeout = 1.0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function wakeup(\int $count = 1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function cmpset(\int $cmp_value, \int $new_value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
