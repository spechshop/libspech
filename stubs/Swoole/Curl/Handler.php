<?php

declare(strict_types=1);

namespace Swoole\Curl;


class Handler {

    
    public function __construct(\string $url = '') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __toString(): \string {
        return "";
    }

    
    public function isAvailable(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setOpt(\int $opt, $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exec() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getInfo() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function errno(): \int {
        return 0;
    }

    
    public function error(): \string {
        return "";
    }

    
    public function reset() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getContent() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
