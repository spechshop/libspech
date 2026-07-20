<?php

declare(strict_types=1);

namespace Swoole\RemoteObject;


class Server {
    public const DEFAULT_PORT = 9567;

    
    public function __construct(\string $host = '127.0.0.1', \int $port = 9567, \array $options = []) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function start(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function onStart() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
