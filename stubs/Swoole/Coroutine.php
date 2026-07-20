<?php

declare(strict_types=1);

namespace Swoole;


class Coroutine {

    
    public static function create(\callable $func, $param = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function defer(\callable $callback) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function set(\array $options) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getOptions(): \array {
        return [];
    }

    
    public static function exists(\int $cid): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function yield(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function cancel(\int $cid, \bool $throw_exception = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function join(\array $cid_array, \float $timeout = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function isCanceled(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function setTimeLimit(\float $timeout): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function suspend(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function resume(\int $cid): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function stats(): \array {
        return [];
    }

    
    public static function getCid(): \int {
        return 0;
    }

    
    public static function getuid(): \int {
        return 0;
    }

    
    public static function getPcid(\int $cid = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getContext(\int $cid = 0): \Swoole\Coroutine\Context {
        return class_exists(\Swoole\Coroutine\Context::class) ? \Swoole\Coroutine\Context::class : \stdClass::class;
    }

    
    public static function getBackTrace(\int $cid = 0, \int $options = 1, \int $limit = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function printBackTrace(\int $cid = 0, \int $options = 0, \int $limit = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getElapsed(\int $cid = 0): \int {
        return 0;
    }

    
    public static function getStackUsage(\int $cid = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function list(): \Swoole\Coroutine\Iterator {
        return class_exists(\Swoole\Coroutine\Iterator::class) ? \Swoole\Coroutine\Iterator::class : \stdClass::class;
    }

    
    public static function listCoroutines(): \Swoole\Coroutine\Iterator {
        return class_exists(\Swoole\Coroutine\Iterator::class) ? \Swoole\Coroutine\Iterator::class : \stdClass::class;
    }

    
    public static function enableScheduler(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function disableScheduler(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getExecuteTime(): \int {
        return 0;
    }

    
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
