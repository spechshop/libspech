<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class Barrier {

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function make(): \Swoole\Coroutine\Barrier {
        return class_exists(\Swoole\Coroutine\Barrier::class) ? \Swoole\Coroutine\Barrier::class : \stdClass::class;
    }

    /**
     * @param-out null $barrier
     */
    public static function wait(\Swoole\Coroutine\Barrier &$barrier, \float $timeout = -1.0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
