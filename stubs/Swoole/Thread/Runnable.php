<?php

declare(strict_types=1);

namespace Swoole\Thread;

/**
 * @since 6.0.0-beta
 */
class Runnable {

    
    public function __construct($running, $index) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function run(\array $args) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
