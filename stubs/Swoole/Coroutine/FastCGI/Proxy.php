<?php

declare(strict_types=1);

namespace Swoole\Coroutine\FastCGI;


class Proxy {

    
    public function __construct(\string $url, \string $documentRoot = '/') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function withTimeout(\float $timeout): \Swoole\Coroutine\FastCGI\Proxy {
        return class_exists(\Swoole\Coroutine\FastCGI\Proxy::class) ? \Swoole\Coroutine\FastCGI\Proxy::class : \stdClass::class;
    }

    
    public function withHttps(\bool $https): \Swoole\Coroutine\FastCGI\Proxy {
        return class_exists(\Swoole\Coroutine\FastCGI\Proxy::class) ? \Swoole\Coroutine\FastCGI\Proxy::class : \stdClass::class;
    }

    
    public function withIndex(\string $index): \Swoole\Coroutine\FastCGI\Proxy {
        return class_exists(\Swoole\Coroutine\FastCGI\Proxy::class) ? \Swoole\Coroutine\FastCGI\Proxy::class : \stdClass::class;
    }

    
    public function getParam(\string $name): \string {
        return "";
    }

    
    public function withParam(\string $name, \string $value): \Swoole\Coroutine\FastCGI\Proxy {
        return class_exists(\Swoole\Coroutine\FastCGI\Proxy::class) ? \Swoole\Coroutine\FastCGI\Proxy::class : \stdClass::class;
    }

    
    public function withoutParam(\string $name): \Swoole\Coroutine\FastCGI\Proxy {
        return class_exists(\Swoole\Coroutine\FastCGI\Proxy::class) ? \Swoole\Coroutine\FastCGI\Proxy::class : \stdClass::class;
    }

    
    public function getParams(): \array {
        return [];
    }

    
    public function withParams(\array $params): \Swoole\Coroutine\FastCGI\Proxy {
        return class_exists(\Swoole\Coroutine\FastCGI\Proxy::class) ? \Swoole\Coroutine\FastCGI\Proxy::class : \stdClass::class;
    }

    
    public function withAddedParams(\array $params): \Swoole\Coroutine\FastCGI\Proxy {
        return class_exists(\Swoole\Coroutine\FastCGI\Proxy::class) ? \Swoole\Coroutine\FastCGI\Proxy::class : \stdClass::class;
    }

    
    public function withStaticFileFilter(\callable $filter): \Swoole\Coroutine\FastCGI\Proxy {
        return class_exists(\Swoole\Coroutine\FastCGI\Proxy::class) ? \Swoole\Coroutine\FastCGI\Proxy::class : \stdClass::class;
    }

    
    public function translateRequest(\Swoole\Http\Request $userRequest): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function translateResponse(\Swoole\FastCGI\HttpResponse $response, \Swoole\Http\Response $userResponse) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function pass(\Swoole\Http\Request|Swoole\FastCGI\HttpRequest $userRequest, \Swoole\Http\Response $userResponse) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * Send content of a static file to the client, if the file is accessible and is not a PHP file.
     *
     * @return bool True if the file doesn't have an extension of 'php', false otherwise. Note that the file may not be
     *              accessible even the return value is true.
     */
    public function staticFileFiltrate(\Swoole\FastCGI\HttpRequest $request, \Swoole\Http\Response $userResponse): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
