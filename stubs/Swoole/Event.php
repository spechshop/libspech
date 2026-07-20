<?php

declare(strict_types=1);

namespace Swoole;


class Event {

    
    public static function add($fd, \callable $read_callback = NULL, \callable $write_callback = NULL, \int $events = 512) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function del($fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function set($fd, \callable $read_callback = NULL, \callable $write_callback = NULL, \int $events = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function isset($fd, \int $events = 1536): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function dispatch(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function defer(\callable $callback): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function cycle(\callable $callback, \bool $before = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function write($fd, \string $data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function wait() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function rshutdown() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function exit() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
