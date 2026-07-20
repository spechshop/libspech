<?php

declare(strict_types=1);

namespace Swoole\Database;


class DetectsLostConnections {

    
    public static function causedByLostConnection(\Throwable $e): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
