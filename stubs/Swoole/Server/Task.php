<?php

declare(strict_types=1);

namespace Swoole\Server;


class Task {

    
    public function finish($data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function pack($data) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function unpack(\string $data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
