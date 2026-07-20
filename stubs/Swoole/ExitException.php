<?php

declare(strict_types=1);

namespace Swoole;


class ExitException {

    
    public function getFlags(): \int {
        return 0;
    }

    
    public function getStatus(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
