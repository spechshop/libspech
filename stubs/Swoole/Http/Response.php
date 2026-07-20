<?php

declare(strict_types=1);

namespace Swoole\Http;


class Response {

    
    public function initHeader(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isWritable(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function cookie(\Swoole\Http\Cookie|string $name_or_object, \string $value = '', \int $expires = 0, \string $path = '/', \string $domain = '', \bool $secure = false, \bool $httponly = false, \string $samesite = '', \string $priority = '', \bool $partitioned = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setCookie(\Swoole\Http\Cookie|string $name_or_object, \string $value = '', \int $expires = 0, \string $path = '/', \string $domain = '', \bool $secure = false, \bool $httponly = false, \string $samesite = '', \string $priority = '', \bool $partitioned = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function rawcookie(\Swoole\Http\Cookie|string $name_or_object, \string $value = '', \int $expires = 0, \string $path = '/', \string $domain = '', \bool $secure = false, \bool $httponly = false, \string $samesite = '', \string $priority = '', \bool $partitioned = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setRawCookie(\Swoole\Http\Cookie|string $name_or_object, \string $value = '', \int $expires = 0, \string $path = '/', \string $domain = '', \bool $secure = false, \bool $httponly = false, \string $samesite = '', \string $priority = '', \bool $partitioned = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function status(\int $http_code, \string $reason = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setStatusCode(\int $http_code, \string $reason = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function header(\string $key, \array|string $value, \bool $format = true): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setHeader(\string $key, \array|string $value, \bool $format = true): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function trailer(\string $key, \string $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function ping(\string $data = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function goaway(\int $error_code = 0, \string $debug_data = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function write(\string $content): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function end(\string $content = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendfile(\string $filename, \int $offset = 0, \int $length = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function redirect(\string $location, \int $http_code = 302): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function detach(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function create(\object|array|int $server = -1, \int $fd = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function upgrade(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function push(\Swoole\WebSocket\Frame|string $data, \int $opcode = 1, \int $flags = 1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recv(\float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function disconnect(\int $code = 1000, \string $reason = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
