<?php

declare(strict_types=1);

namespace Swoole\Database;


class ObjectProxy {

    
    public function __clone() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
