<?php

declare(strict_types=1);

namespace Swoole\Database;

/**
 * @method \mysqli|MysqliProxy get()
 * @method void put(mysqli|MysqliProxy $connection)
 */
class MysqliPool {
    public const DEFAULT_SIZE = 64;

    
    public function __construct(\Swoole\Database\MysqliConfig $config, \int $size = 64) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
