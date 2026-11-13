<?php

declare(strict_types=1);

namespace Swoole;

final class Buffer
{

    public function __construct(int $capacity) {}

    public function getCapacity(): int {}

    public function getLength(): int {}

    public function clear(): void {}

    public function toString(): string {}
}
