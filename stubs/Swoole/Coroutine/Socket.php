<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class Socket {

    
    public function __construct(\int $domain, \int $type, \int $protocol = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function bind(\string $address, \int $port = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function listen(\int $backlog = 512): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function accept(\float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function connect(\string $host, \int $port = 0, \float $timeout = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function checkLiveness(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getBoundCid(\int $event): \int {
        return 0;
    }

    
    public function peek(\int $length = 65536) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recv(\int $length = 65536, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recvAll(\int $length = 65536, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recvLine(\int $length = 65536, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recvWithBuffer(\int $length = 65536, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recvPacket(\float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function send(\string $data, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function readVector(\array $io_vector, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function readVectorAll(\array $io_vector, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function writeVector(\array $io_vector, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function writeVectorAll(\array $io_vector, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendFile(\string $file, \int $offset = 0, \int $length = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendAll(\string $data, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function recvfrom(&$peername, \float $timeout = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendto(\string $addr, \int $port, \string $data) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getOption(\int $level, \int $opt_name): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setProtocol(\array $settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setOption(\int $level, \int $opt_name, $opt_value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sslHandshake(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function shutdown(\int $how = 2): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function cancel(\int $event = 512): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getpeername() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getsockname() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isClosed(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function import($stream) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
