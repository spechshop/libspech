<?php

declare(strict_types=1);

namespace Swoole;

class Server
{
    private $onStart = NULL;
    private $onBeforeShutdown = NULL;
    private $onShutdown = NULL;
    private $onWorkerStart = NULL;
    private $onWorkerStop = NULL;
    private $onBeforeReload = NULL;
    private $onAfterReload = NULL;
    private $onWorkerExit = NULL;
    private $onWorkerError = NULL;
    private $onTask = NULL;
    private $onFinish = NULL;
    private $onManagerStart = NULL;
    private $onManagerStop = NULL;
    private $onPipeMessage = NULL;
    public $setting = NULL;
    public $connections = NULL;
    public $host = '';
    public $port = 0;
    public $type = 0;
    public $ssl = false;
    public $mode = 0;
    public $ports = NULL;
    public $master_pid = 0;
    public $manager_pid = 0;
    public $worker_id = -1;
    public $taskworker = false;
    public $worker_pid = 0;
    public $stats_timer = NULL;
    public $admin_server = NULL;
    public $bootstrap = '';

    public function __construct(string $host = '0.0.0.0', int $port = 0, int $mode = 1, int $sock_type = 1) {}

    public function __destruct() {}

    public function listen(string $host, int $port, int $sock_type): \Swoole\Server\Port|false {}

    public function addlistener(string $host, int $port, int $sock_type): \Swoole\Server\Port|false {}

    public function on(string $event_name, callable $callback): bool {}

    public function getCallback(string $event_name): \Closure|array|string|null {}

    public function set(array $settings): bool {}

    public function start(): bool {}

    public function send(string|int $fd, string $send_data, int $serverSocket = -1): bool {}

    public function sendto(string $ip, int $port, string $send_data, int $server_socket = -1): bool {}

    public function sendwait(int $conn_fd, string $send_data): bool {}

    public function exists(int $fd): bool {}

    public function exist(int $fd): bool {}

    public function protect(int $fd, bool $is_protected = true): bool {}

    public function sendfile(int $conn_fd, string $filename, int $offset = 0, int $length = 0): bool {}

    public function close(int $fd, bool $reset = false): bool {}

    public function confirm(int $fd): bool {}

    public function pause(int $fd): bool {}

    public function resume(int $fd): bool {}

    public function task(mixed $data, int $taskWorkerIndex = -1, ?callable $finishCallback = NULL): int|false {}

    public function taskwait(mixed $data, float $timeout = 0.5, int $taskWorkerIndex = -1): mixed {}

    public function taskWaitMulti(array $tasks, float $timeout = 0.5): array|false {}

    public function taskCo(array $tasks, float $timeout = 0.5): array|false {}

    public function finish(mixed $data): bool {}

    public function reload(bool $only_reload_taskworker = false): bool {}

    public function shutdown(): bool {}

    public function stop(int $workerId = -1): bool {}

    public function getLastError(): int {}

    public function heartbeat(bool $ifCloseConnection = true): array|false {}

    public function getClientInfo(int $fd, int $reactor_id = -1, bool $ignoreError = false): array|false {}

    public function getClientList(int $start_fd = 0, int $find_count = 10): array|false {}

    public function getWorkerId(): int|false {}

    public function getWorkerPid(int $worker_id = -1): int|false {}

    public function getWorkerStatus(int $worker_id = -1): int|false {}

    public function getManagerPid(): int {}

    public function getMasterPid(): int {}

    public function connection_info(int $fd, int $reactor_id = -1, bool $ignoreError = false): array|false {}

    public function connection_list(int $start_fd = 0, int $find_count = 10): array|false {}

    public function sendMessage(mixed $message, int $dst_worker_id): bool {}

    public function command(string $name, int $process_id, int $process_type, mixed $data, bool $json_decode = true): array|string|false {}

    public function addCommand(string $name, int $accepted_process_types, callable $callback): bool {}

    public function addProcess(\Swoole\Process $process): int|false {}

    public function stats(): array {}

    public function getSocket(int $port = 0): \Socket|false {}

    public function bind(int $fd, int $uid): bool {}
}
