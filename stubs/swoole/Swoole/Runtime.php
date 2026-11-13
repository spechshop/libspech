<?php

declare(strict_types=1);

namespace Swoole;

class Runtime
{

    public static function enableCoroutine(int $flags = 2143287295): bool {}

    public static function getHookFlags(): int {}

    public static function setHookFlags(int $flags): bool {}
}
