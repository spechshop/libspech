<?php

declare(strict_types=1);

namespace Swoole;


class Client {
    public const MSG_OOB = 1;
    public const MSG_PEEK = 2;
    public const MSG_DONTWAIT = 64;
    public const MSG_WAITALL = 256;
    public const SHUT_RDWR = 2;
    public const SHUT_RD = 0;
    public const SHUT_WR = 1;

    
    public function __construct(\int $type, \bool $async = false, \string $id = '') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function connect(\string $host, \int $port = 0, \float $timeout = 0.5, \int $sock_flag = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recv(\int $size = 65536, \int $flag = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function send(\string $data, \int $flag = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendfile(\string $filename, \int $offset = 0, \int $length = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendto(\string $ip, \int $port, \string $data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function shutdown(\int $how): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function enableSSL(\callable $onSslReady = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getPeerCert() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function verifyPeerCert(): \mixed {
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

    
    public function close(\bool $force = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getSocket() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
