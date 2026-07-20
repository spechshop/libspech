<?php

declare(strict_types=1);

namespace Swoole\Coroutine\FastCGI;


class Client {

    
    public function __construct(\string $host, \int $port = 0, \bool $ssl = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return ($request is HttpRequest ? HttpResponse : Response)
     * @throws Exception
     */
    public function execute(\Swoole\FastCGI\Request $request, \float $timeout = -1.0): \Swoole\FastCGI\Response {
        return class_exists(\Swoole\FastCGI\Response::class) ? \Swoole\FastCGI\Response::class : \stdClass::class;
    }

    
    public static function parseUrl(\string $url): \array {
        return [];
    }

    
    public static function call(\string $url, \string $path, $data = '', \float $timeout = -1.0): \string {
        return "";
    }
}
