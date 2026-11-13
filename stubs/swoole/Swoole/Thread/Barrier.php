<?php

declare(strict_types=1);

namespace Swoole\Thread;

final class Barrier
{

    public function __construct(int $count) {}

    public function wait(): void {}
}
