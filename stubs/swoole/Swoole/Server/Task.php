<?php

declare(strict_types=1);

namespace Swoole\Server;

final class Task
{
    public $data = NULL;
    public $dispatch_time = 0.0;
    public $id = -1;
    public $worker_id = -1;
    public $flags = 0;

    public function finish(mixed $data): bool {}

    public static function pack(mixed $data): string|false {}

    public static function unpack(string $data): mixed {}
}
