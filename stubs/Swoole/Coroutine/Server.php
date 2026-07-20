<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class Server {

    /**
     * Server constructor.
     * @throws Exception
     */
    public function __construct(\string $host, \int $port = 0, \bool $ssl = false, \bool $reuse_port = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $setting) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function handle(\callable $fn) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function shutdown(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function start(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
