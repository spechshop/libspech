<?php

declare(strict_types=1);

namespace Swoole\Coroutine\Http;


class ClientProxy {

    
    public function __construct(\string $body, \int $statusCode, \array $headers, \array $cookies) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getBody(): \string {
        return "";
    }

    
    public function getStatusCode(): \int {
        return 0;
    }

    
    public function getHeaders(): \array {
        return [];
    }

    
    public function getCookies(): \array {
        return [];
    }
}
