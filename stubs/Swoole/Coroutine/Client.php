<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class Client {
    public const MSG_OOB = 1;
    public const MSG_PEEK = 2;
    public const MSG_DONTWAIT = 64;
    public const MSG_WAITALL = 256;

    
    public function __construct(\int $type) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function connect(\string $host, \int $port = 0, \float $timeout = 0, \int $sock_flag = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recv(\float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function peek(\int $length = 65535) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function send(\string $data, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendfile(\string $filename, \int $offset = 0, \int $length = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendto(\string $address, \int $port, \string $data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recvfrom(\int $length, &$address, &$port = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function enableSSL(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getPeerCert() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function verifyPeerCert(\bool $allow_self_signed = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isConnected(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getsockname() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getpeername() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exportSocket() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
