<?php

declare(strict_types=1);

namespace Swoole\Thread;

final class ArrayList implements \ArrayAccess, \Countable
{
    public readonly mixed $id;

    public function __construct(?array $array = NULL) {}

    public function offsetGet(mixed $key): mixed {}

    public function offsetExists(mixed $key): bool {}

    public function offsetSet(mixed $key, mixed $value): void {}

    public function offsetUnset(mixed $key): void {}

    public function find(mixed $value): int {}

    public function incr(mixed $key, mixed $value = 1): mixed {}

    public function decr(mixed $key, mixed $value = 1): mixed {}

    public function clean(): void {}

    public function count(): int {}

    public function toArray(): array {}

    public function sort(): void {}
}
