<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class Scheduler {

    
    public function add(\callable $func, $param = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function parallel(\int $n, \callable $func, $param = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getOptions(): \array {
        return [];
    }

    
    public function start(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
