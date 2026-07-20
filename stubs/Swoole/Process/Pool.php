<?php

declare(strict_types=1);

namespace Swoole\Process;


class Pool {

    
    public function __construct(\int $worker_num, \int $ipc_type = 0, \int $msgqueue_key = 0, \bool $enable_coroutine = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function on(\string $name, \callable $callback): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getProcess(\int $work_id = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function listen(\string $host, \int $port = 0, \int $backlog = 2048): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function write(\string $data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendMessage(\string $data, \int $dst_worker_id): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function detach(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function start(): \false {
        return class_exists(\false::class) ? \false::class : \stdClass::class;
    }

    
    public function stop() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function shutdown(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
