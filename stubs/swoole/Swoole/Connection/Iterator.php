<?php

declare(strict_types=1);

namespace Swoole\Connection;

class Iterator implements \Iterator, \Traversable, \ArrayAccess, \Countable
{

    public function __construct() {}

    public function __destruct() {}

    public function rewind(): void {}

    public function next(): void {}

    public function current(): mixed {}

    public function key(): mixed {}

    public function valid(): bool {}

    public function count(): int {}

    public function offsetExists(mixed $fd): bool {}

    public function offsetGet(mixed $fd): mixed {}

    public function offsetSet(mixed $fd, mixed $value): void {}

    public function offsetUnset(mixed $fd): void {}
}
