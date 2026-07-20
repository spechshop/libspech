<?php

declare(strict_types=1);

namespace Swoole\MongoDB;


class Client {
    public const DEFAULT_URI = 'mongodb://127.0.0.1/';

    
    public function __construct(\string $uri = 'mongodb://127.0.0.1/', \array $uriOptions = [], \array $driverOptions = []) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __call(\string $method, \array $args) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __get(\string $property) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __set(\string $property, $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __toString(): \string {
        return "";
    }

    
    public function __invoke($args = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetGet($offset): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Exception
     */
    public function offsetSet($offset, $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Exception
     */
    public function offsetUnset($offset) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetExists($offset): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function current(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function next() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function key(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function valid(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function rewind() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }
}
