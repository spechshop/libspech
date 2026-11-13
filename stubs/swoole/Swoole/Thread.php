<?php

declare(strict_types=1);

namespace Swoole;

final class Thread
{
    public const HARDWARE_CONCURRENCY = 16;
    public const API_NAME = 'POSIX Threads';
    public const SCHED_OTHER = 0;
    public const SCHED_FIFO = 1;
    public const SCHED_RR = 2;
    public const SCHED_BATCH = 3;
    public const SCHED_IDLE = 5;
    public const SCHED_DEADLINE = 6;
    public readonly mixed $id;

    public function __construct(string $script_file, mixed ...$args) {}

    public function isAlive(): bool {}

    public function join(): bool {}

    public function joinable(): bool {}

    public function getExitStatus(): int {}

    public function detach(): bool {}

    public static function getArguments(): ?array {}

    public static function getId(): int {}

    public static function getInfo(): array {}

    public static function activeCount(): int {}

    public static function yield(): void {}

    public static function setName(string $name): bool {}

    public static function setAffinity(array $cpu_settings): bool {}

    public static function getAffinity(): array {}

    public static function setPriority(int $priority, int $policy = -1): bool {}

    public static function getPriority(): array {}

    public static function getNativeId(): int {}
}
