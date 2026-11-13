<?php

declare(strict_types=1);

namespace Swoole;

class Table implements \Iterator, \Traversable, \Countable
{
    public const TYPE_INT = 1;
    public const TYPE_STRING = 3;
    public const TYPE_FLOAT = 2;
    public $size = NULL;
    public $memorySize = NULL;

    public function __construct(int $table_size, float $conflict_proportion = 0.2) {}

    public function column(string $name, int $type, int $size = 0): bool {}

    public function create(): bool {}

    public function destroy(): bool {}

    public function set(string $key, array $value): bool {}

    public function add(string $key, array $values): bool {}

    public function update(string $key, array $values): bool {}

    public function cmpset(string $key, array $expected, array $values): bool {}

    public function get(string $key, ?string $field = NULL): array|string|int|float|false {}

    public function getdel(string $key, ?string $field = NULL): array|string|int|float|false {}

    public function count(): int {}

    public function del(string $key): bool {}

    public function delete(string $key): bool {}

    public function cmpdel(string $key, array $expected): bool {}

    public function exists(string $key): bool {}

    public function exist(string $key): bool {}

    public function incr(string $key, string $column, int|float $incrby = 1): int|float|false {}

    public function decr(string $key, string $column, int|float $incrby = 1): int|float|false {}

    public function getSize(): int {}

    public function getMemorySize(): int {}

    public function stats(): array|false {}

    public function rewind(): void {}

    public function valid(): bool {}

    public function next(): void {}

    public function current(): mixed {}

    public function key(): mixed {}
}
