<?php

declare(strict_types=1);

namespace Swoole\Http;


class Request {

    
    public function getContent() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function rawContent() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getData() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function create(\array $options = []): \Swoole\Http\Request {
        return class_exists(\Swoole\Http\Request::class) ? \Swoole\Http\Request::class : \stdClass::class;
    }

    
    public function parse(\string $data) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isCompleted(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getMethod() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
