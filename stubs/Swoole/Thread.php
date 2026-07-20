<?php

declare(strict_types=1);

namespace Swoole;


class Thread {
    public const HARDWARE_CONCURRENCY = 12;
    public const API_NAME = 'POSIX Threads';
    public const SCHED_OTHER = 0;
    public const SCHED_FIFO = 1;
    public const SCHED_RR = 2;
    public const SCHED_BATCH = 3;
    public const SCHED_IDLE = 5;
    public const SCHED_DEADLINE = 6;

    
    public function __construct(\string $script_file, $args = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function isAlive(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function join(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function joinable(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getExitStatus(): \int {
        return 0;
    }

    
    public function detach(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getArguments(): \array {
        return [];
    }

    
    public static function getId(): \int {
        return 0;
    }

    
    public static function getInfo(): \array {
        return [];
    }

    
    public static function activeCount(): \int {
        return 0;
    }

    
    public static function yield() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function setName(\string $name): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function setAffinity(\array $cpu_settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getAffinity(): \array {
        return [];
    }

    
    public static function setPriority(\int $priority, \int $policy = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getPriority(): \array {
        return [];
    }

    
    public static function getNativeId(): \int {
        return 0;
    }
}
