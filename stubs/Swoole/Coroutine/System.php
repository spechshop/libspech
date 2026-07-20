<?php

declare(strict_types=1);

namespace Swoole\Coroutine;


class System {

    
    public static function gethostbyname(\string $domain_name, \int $type = 2, \float $timeout = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function dnsLookup(\string $domain_name, \float $timeout = 60, \int $type = 2) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function exec(\string $command, \bool $get_error_stream = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function sleep(\float $seconds): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getaddrinfo(\string $domain, \int $family = 2, \int $socktype = 1, \int $protocol = 6, \string $service = NULL, \float $timeout = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function statvfs(\string $path): \array {
        return [];
    }

    
    public static function readFile(\string $filename, \int $flag = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function writeFile(\string $filename, \string $fileContent, \int $flags = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function wait(\float $timeout = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function waitPid(\int $pid, \float $timeout = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function waitSignal(\array|int $signals, \float $timeout = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function waitEvent($socket, \int $events = 512, \float $timeout = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
