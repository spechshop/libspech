<?php

declare(strict_types=1);

namespace Swoole;

class Coroutine
{

    public static function create(callable $func, mixed ...$param): int|false {}

    public static function defer(callable $callback): void {}

    public static function set(array $options): void {}

    public static function getOptions(): ?array {}

    public static function exists(int $cid): bool {}

    public static function yield(): bool {}

    public static function cancel(int $cid, bool $throw_exception = false): bool {}

    public static function join(array $cid_array, float $timeout = -1): bool {}

    public static function isCanceled(): bool {}

    public static function setTimeLimit(float $timeout): bool {}

    public static function suspend(): bool {}

    public static function resume(int $cid): bool {}

    public static function stats(): array {}

    public static function getCid(): int {}

    public static function getuid(): int {}

    public static function getPcid(int $cid = 0): int|false {}

    public static function getContext(int $cid = 0): ?\Swoole\Coroutine\Context {}

    public static function getBackTrace(int $cid = 0, int $options = 1, int $limit = 0): array|false {}

    public static function printBackTrace(int $cid = 0, int $options = 0, int $limit = 0): void {}

    public static function getElapsed(int $cid = 0): int {}

    public static function getStackUsage(int $cid = 0): int|false {}

    public static function list(): \Swoole\Coroutine\Iterator {}

    public static function listCoroutines(): \Swoole\Coroutine\Iterator {}

    public static function enableScheduler(): bool {}

    public static function disableScheduler(): bool {}

    public static function getExecuteTime(): int {}

    public static function gethostbyname(string $domain_name, int $type = 2, float $timeout = -1): string|false {}

    public static function dnsLookup(string $domain_name, float $timeout = 60, int $type = 2): string|false {}

    public static function exec(string $command, bool $get_error_stream = false): array|false {}

    public static function sleep(float $seconds): bool {}

    public static function getaddrinfo(string $domain, int $family = 2, int $socktype = 1, int $protocol = 6, ?string $service = NULL, float $timeout = -1): array|bool {}

    public static function statvfs(string $path): array|false {}

    public static function readFile(string $filename, int $flag = 0): string|false {}

    public static function writeFile(string $filename, string $fileContent, int $flags = 0): int|false {}

    public static function wait(float $timeout = -1): array|false {}

    public static function waitPid(int $pid, float $timeout = -1): array|false {}

    public static function waitSignal(array|int $signals, float $timeout = -1): int|false {}

    public static function waitEvent(mixed $socket, int $events = 512, float $timeout = -1): int|false {}
}
