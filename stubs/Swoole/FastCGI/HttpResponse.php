<?php

declare(strict_types=1);

namespace Swoole\FastCGI;


class HttpResponse {

    /**
     * @param array<Stdout|Stderr|EndRequest> $records
     */
    public function __construct(\array $records = []) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getStatusCode(): \int {
        return 0;
    }

    
    public function withStatusCode(\int $statusCode): \Swoole\FastCGI\HttpResponse {
        return class_exists(\Swoole\FastCGI\HttpResponse::class) ? \Swoole\FastCGI\HttpResponse::class : \stdClass::class;
    }

    
    public function getReasonPhrase(): \string {
        return "";
    }

    
    public function withReasonPhrase(\string $reasonPhrase): \Swoole\FastCGI\HttpResponse {
        return class_exists(\Swoole\FastCGI\HttpResponse::class) ? \Swoole\FastCGI\HttpResponse::class : \stdClass::class;
    }

    
    public function getHeader(\string $name): \string {
        return "";
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): \array {
        return [];
    }

    
    public function withHeader(\string $name, \string $value): \Swoole\FastCGI\HttpResponse {
        return class_exists(\Swoole\FastCGI\HttpResponse::class) ? \Swoole\FastCGI\HttpResponse::class : \stdClass::class;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(\array $headers): \Swoole\FastCGI\HttpResponse {
        return class_exists(\Swoole\FastCGI\HttpResponse::class) ? \Swoole\FastCGI\HttpResponse::class : \stdClass::class;
    }

    /**
     * @return array<string>
     */
    public function getSetCookieHeaderLines(): \array {
        return [];
    }

    
    public function withSetCookieHeaderLine(\string $value): \Swoole\FastCGI\HttpResponse {
        return class_exists(\Swoole\FastCGI\HttpResponse::class) ? \Swoole\FastCGI\HttpResponse::class : \stdClass::class;
    }
}
