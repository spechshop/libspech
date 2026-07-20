<?php

declare(strict_types=1);

namespace Swoole\Connection;


class Iterator {

    
    public function __construct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function rewind() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function next() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function current(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function key(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function valid(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }

    
    public function offsetExists($fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetGet($fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetSet($fd, $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetUnset($fd) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
