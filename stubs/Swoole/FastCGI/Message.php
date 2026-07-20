<?php

declare(strict_types=1);

namespace Swoole\FastCGI;


class Message {

    
    public function getParam(\string $name): \string {
        return "";
    }

    
    public function withParam(\string $name, \string $value): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function withoutParam(\string $name): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function getParams(): \array {
        return [];
    }

    
    public function withParams(\array $params): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function withAddedParams(\array $params): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function getBody(): \string {
        return "";
    }

    
    public function withBody(\Stringable|string $body): \Swoole\FastCGI\Message {
        return class_exists(\Swoole\FastCGI\Message::class) ? \Swoole\FastCGI\Message::class : \stdClass::class;
    }

    
    public function getError(): \string {
        return "";
    }

    
    public function withError(\string $error): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }
}
