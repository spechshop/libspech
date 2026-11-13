<?php

declare(strict_types=1);

namespace Swoole\Thread;

final class Lock
{
    public const MUTEX = 3;
    public const RWLOCK = 1;
    public const SPINLOCK = 5;
    public $errCode = 0;

    public function __construct(int $type = 3) {}

    public function lock(int $operation = 2, float $timeout = -1): bool {}

    public function unlock(): bool {}
}
