<?php

declare(strict_types=1);

namespace Swoole\Coroutine\Http;


class Server {

    
    public function __construct(\string $host, \int $port = 0, \bool $ssl = false, \bool $reuse_port = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function handle(\string $pattern, \callable $callback): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function start(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function shutdown() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
