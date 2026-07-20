<?php

declare(strict_types=1);

namespace Swoole;


class Runtime {

    
    public static function enableCoroutine(\int $flags = 2143287295): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getHookFlags(): \int {
        return 0;
    }

    
    public static function setHookFlags(\int $flags): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
