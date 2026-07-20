<?php

declare(strict_types=1);

namespace Swoole;


class Process {
    public const IPC_NOWAIT = 256;
    public const PIPE_MASTER = 1;
    public const PIPE_WORKER = 2;
    public const PIPE_READ = 3;
    public const PIPE_WRITE = 4;
    public const PIPE_TYPE_NONE = 0;
    public const PIPE_TYPE_STREAM = 1;
    public const PIPE_TYPE_DGRAM = 2;

    
    public function __construct(\callable $callback, \bool $redirect_stdin_and_stdout = false, \int $pipe_type = 2, \bool $enable_coroutine = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function wait(\bool $blocking = true) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function signal(\int $signal_no, \callable $callback = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function alarm(\int $usec, \int $type = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function kill(\int $pid, \int $signal_no = 15): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function daemon(\bool $nochdir = true, \bool $noclose = true, \array $pipes = []): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function setAffinity(\array $cpu_settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getAffinity(): \array {
        return [];
    }

    
    public function setPriority(\int $which, \int $priority, \int $who = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getPriority(\int $which, \int $who = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setTimeout(\float $seconds): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setBlocking(\bool $blocking): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function useQueue(\int $key = 0, \int $mode = 2, \int $capacity = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function statQueue() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function freeQueue(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function start() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function write(\string $data) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(\int $which = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function read(\int $size = 8192) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function push(\string $data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function pop(\int $size = 65536) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exit(\int $exit_code = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exec(\string $exec_file, \array $args): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exportSocket() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function name(\string $process_name): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
