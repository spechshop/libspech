<?php

declare(strict_types=1);

namespace Swoole;

class Event
{

    public static function add(mixed $fd, ?callable $read_callback = NULL, ?callable $write_callback = NULL, int $events = 512): int|false {}

    public static function del(mixed $fd): bool {}

    public static function set(mixed $fd, ?callable $read_callback = NULL, ?callable $write_callback = NULL, int $events = 0): bool {}

    public static function isset(mixed $fd, int $events = 1536): bool {}

    public static function dispatch(): bool {}

    public static function defer(callable $callback): bool {}

    public static function cycle(?callable $callback, bool $before = false): bool {}

    public static function write(mixed $fd, string $data): bool {}

    public static function wait(): void {}

    public static function rshutdown(): void {}

    public static function exit(): void {}
}
