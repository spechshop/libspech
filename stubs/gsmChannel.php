<?php

declare(strict_types=1);


class gsmChannel {

    
    public function __construct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function encode(\string $input) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function decode(\string $input) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function info(): \array {
        return [];
    }

    
    public function close(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
