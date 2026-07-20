<?php

declare(strict_types=1);

namespace Swoole\Database;

/**
 * @method \Redis get(float $timeout = -1)
 * @method void put(Redis $connection)
 */
class RedisPool {
    public const DEFAULT_SIZE = 64;

    
    public function __construct(\Swoole\Database\RedisConfig $config, \int $size = 64) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
