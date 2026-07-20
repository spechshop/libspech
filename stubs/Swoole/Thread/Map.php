<?php

declare(strict_types=1);

namespace Swoole\Thread;


class Map {

    
    public function __construct(\array $array = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetGet($key): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetExists($key): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetSet($key, $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetUnset($key) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function find($value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }

    
    public function incr($key, $value = 1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function decr($key, $value = 1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function add($key, $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function update($key, $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function clean() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function keys(): \array {
        return [];
    }

    
    public function values(): \array {
        return [];
    }

    
    public function toArray(): \array {
        return [];
    }

    
    public function sort() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
