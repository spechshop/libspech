<?php

declare(strict_types=1);

namespace Swoole\Coroutine\Http;


class Client {

    
    public function __construct(\string $host, \int $port = 0, \bool $ssl = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getDefer(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setDefer(\bool $defer = true): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setMethod(\string $method): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setHeaders(\array $headers): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setBasicAuth(\string $username, \string $password) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setCookies(\array $cookies): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setData(\array|string $data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function addFile(\string $path, \string $name, \string $type = NULL, \string $filename = NULL, \int $offset = 0, \int $length = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function addData(\string $path, \string $name, \string $type = NULL, \string $filename = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function execute(\string $path): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getpeername() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getsockname() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function get(\string $path): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function post(\string $path, $data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function download(\string $path, \string $file, \int $offset = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getBody() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getHeaders() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getCookies() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getStatusCode() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getHeaderOut() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getPeerCert() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function upgrade(\string $path): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function push($data, \int $opcode = 1, \int $flags = 1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recv(\float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function ping(\string $data = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function disconnect(\int $code = 1000, \string $reason = ''): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
