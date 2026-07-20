<?php

declare(strict_types=1);

namespace Swoole\Server;


class Port {

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function on(\string $event_name, \callable $callback): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getCallback(\string $event_name): \Closure {
        return class_exists(\Closure::class) ? \Closure::class : \stdClass::class;
    }

    
    public function getSocket() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
