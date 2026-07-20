<?php

declare(strict_types=1);

namespace Swoole\Http;


class Cookie {

    
    public function __construct(\bool $encode = true) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function withName(\string $name): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withValue(\string $value = ''): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withExpires(\int $expires = 0): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withPath(\string $path = '/'): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withDomain(\string $domain = ''): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withSecure(\bool $secure = false): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withHttpOnly(\bool $httpOnly = false): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withSameSite(\string $sameSite = ''): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withPriority(\string $priority = ''): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function withPartitioned(\bool $partitioned = false): \Swoole\Http\Cookie {
        return class_exists(\Swoole\Http\Cookie::class) ? \Swoole\Http\Cookie::class : \stdClass::class;
    }

    
    public function toString() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function toArray(): \array {
        return [];
    }

    
    public function reset() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
