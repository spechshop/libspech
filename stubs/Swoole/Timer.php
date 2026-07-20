<?php

declare(strict_types=1);

namespace Swoole;


class Timer {

    
    public static function tick(\int $ms, \callable $callback, $params = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function after(\int $ms, \callable $callback, $params = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function exists(\int $timer_id): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function info(\int $timer_id): \array {
        return [];
    }

    
    public static function stats(): \array {
        return [];
    }

    
    public static function list(): \Swoole\Timer\Iterator {
        return class_exists(\Swoole\Timer\Iterator::class) ? \Swoole\Timer\Iterator::class : \stdClass::class;
    }

    
    public static function clear(\int $timer_id): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function clearAll(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
