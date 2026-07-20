<?php

declare(strict_types=1);

namespace Swoole\Database;

/**
 * @method void put(PDO|PDOProxy $connection)
 */
class PDOPool {
    public const DEFAULT_SIZE = 64;

    
    public function __construct(\Swoole\Database\PDOConfig $config, \int $size = 64) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * Get a PDO connection from the pool. The PDO connection (a PDO object) is wrapped in a PDOProxy object returned.
     *
     * @param float $timeout > 0 means waiting for the specified number of seconds. other means no waiting.
     * @return PDOProxy|false Returns a PDOProxy object from the pool, or false if the pool is full and the timeout is reached.
     *                        {@inheritDoc}
     */
    public function get(\float $timeout = -1.0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
