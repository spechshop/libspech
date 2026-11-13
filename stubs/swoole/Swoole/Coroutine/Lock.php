<?php

declare(strict_types=1);

namespace Swoole\Coroutine;

class Lock
{
    public $errCode = 0;

    public function __construct(bool $shared = false) {}

    public function lock(int $operation = 2): bool {}

    public function unlock(): bool {}
}
