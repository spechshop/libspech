<?php

declare(strict_types=1);

namespace Swoole;

class Process
{
    public const IPC_NOWAIT = 256;
    public const PIPE_MASTER = 1;
    public const PIPE_WORKER = 2;
    public const PIPE_READ = 3;
    public const PIPE_WRITE = 4;
    public const PIPE_TYPE_NONE = 0;
    public const PIPE_TYPE_STREAM = 1;
    public const PIPE_TYPE_DGRAM = 2;
    public $pipe = NULL;
    public $msgQueueId = NULL;
    public $msgQueueKey = NULL;
    public $pid = NULL;
    public $id = NULL;
    private $callback = NULL;

    public function __construct(callable $callback, bool $redirect_stdin_and_stdout = false, int $pipe_type = 2, bool $enable_coroutine = false) {}

    public function __destruct() {}

    public static function wait(bool $blocking = true): array|false {}

    public static function signal(int $signal_no, ?callable $callback = NULL): bool {}

    public static function alarm(int $usec, int $type = 0): bool {}

    public static function kill(int $pid, int $signal_no = 15): bool {}

    public static function daemon(bool $nochdir = true, bool $noclose = true, array $pipes = array (
)): bool {}

    public static function setAffinity(array $cpu_settings): bool {}

    public static function getAffinity(): array {}

    public function setPriority(int $which, int $priority, ?int $who = NULL): bool {}

    public function getPriority(int $which, ?int $who = NULL): int|false {}

    public function set(array $settings): void {}

    public function setTimeout(float $seconds): bool {}

    public function setBlocking(bool $blocking): bool {}

    public function useQueue(int $key = 0, int $mode = 2, int $capacity = -1): bool {}

    public function statQueue(): array|false {}

    public function freeQueue(): bool {}

    public function start(): int|bool {}

    public function write(string $data): int|false {}

    public function close(int $which = 0): bool {}

    public function read(int $size = 8192): string|false {}

    public function push(string $data): bool {}

    public function pop(int $size = 65536): string|false {}

    public function exit(int $exit_code = 0): void {}

    public function exec(string $exec_file, array $args): bool {}

    public function exportSocket(): \Swoole\Coroutine\Socket|false {}

    public function name(string $process_name): bool {}
}
