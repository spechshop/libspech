<?php

declare(strict_types=1);

namespace Swoole\Database;

/**
 * @method \PDO __getObject()
 */
class PDOProxy {

    
    public function __construct(\callable $constructor) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __call(\string $name, \array $arguments) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getRound(): \int {
        return 0;
    }

    
    public function reconnect() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setAttribute(\int $attribute, $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function inTransaction(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function reset() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
