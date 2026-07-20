<?php

declare(strict_types=1);

namespace Swoole\Coroutine\Http2;


class Client {

    
    public function __construct(\string $host, \int $port = 80, \bool $open_ssl = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function connect(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function stats(\string $key = '') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isStreamExist(\int $stream_id): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function send(\Swoole\Http2\Request $request) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function write(\int $stream_id, $data, \bool $end_stream = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recv(\float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function read(\float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function goaway(\int $error_code = 0, \string $debug_data = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function ping(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
