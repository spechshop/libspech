<?php

declare(strict_types=1);

namespace Swoole;


class Table {
    public const TYPE_INT = 1;
    public const TYPE_STRING = 3;
    public const TYPE_FLOAT = 2;

    
    public function __construct(\int $table_size, \float $conflict_proportion = 0.2) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function column(\string $name, \int $type, \int $size = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function create(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function destroy(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\string $key, \array $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function get(\string $key, \string $field = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }

    
    public function del(\string $key): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function delete(\string $key): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exists(\string $key): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exist(\string $key): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function incr(\string $key, \string $column, \int|float $incrby = 1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function decr(\string $key, \string $column, \int|float $incrby = 1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getSize(): \int {
        return 0;
    }

    
    public function getMemorySize(): \int {
        return 0;
    }

    
    public function stats() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function rewind() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function valid(): \mixed {
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
}
