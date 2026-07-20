<?php

declare(strict_types=1);

namespace Swoole;


class Server {

    
    public function __construct(\string $host = '0.0.0.0', \int $port = 0, \int $mode = 1, \int $sock_type = 1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function listen(\string $host, \int $port, \int $sock_type) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function addlistener(\string $host, \int $port, \int $sock_type) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function on(\string $event_name, \callable $callback): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getCallback(\string $event_name) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set(\array $settings): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function start(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function send(\string|int $fd, \string $send_data, \int $serverSocket = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendto(\string $ip, \int $port, \string $send_data, \int $server_socket = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendwait(\int $conn_fd, \string $send_data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exists(\int $fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exist(\int $fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function protect(\int $fd, \bool $is_protected = true): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendfile(\int $conn_fd, \string $filename, \int $offset = 0, \int $length = 0): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function close(\int $fd, \bool $reset = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function confirm(\int $fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function pause(\int $fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function resume(\int $fd): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function task($data, \int $taskWorkerIndex = -1, \callable $finishCallback = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function taskwait($data, \float $timeout = 0.5, \int $taskWorkerIndex = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function taskWaitMulti(\array $tasks, \float $timeout = 0.5) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function taskCo(\array $tasks, \float $timeout = 0.5) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function finish($data): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function reload(\bool $only_reload_taskworker = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function shutdown(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function stop(\int $workerId = -1): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getLastError(): \int {
        return 0;
    }

    
    public function heartbeat(\bool $ifCloseConnection = true) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getClientInfo(\int $fd, \int $reactor_id = -1, \bool $ignoreError = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getClientList(\int $start_fd = 0, \int $find_count = 10) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getWorkerId() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getWorkerPid(\int $worker_id = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getWorkerStatus(\int $worker_id = -1) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getManagerPid(): \int {
        return 0;
    }

    
    public function getMasterPid(): \int {
        return 0;
    }

    
    public function connection_info(\int $fd, \int $reactor_id = -1, \bool $ignoreError = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function connection_list(\int $start_fd = 0, \int $find_count = 10) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function sendMessage($message, \int $dst_worker_id): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function command(\string $name, \int $process_id, \int $process_type, $data, \bool $json_decode = true) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function addCommand(\string $name, \int $accepted_process_types, \callable $callback): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function addProcess(\Swoole\Process $process) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function stats(): \array {
        return [];
    }

    
    public function getSocket(\int $port = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function bind(\int $fd, \int $uid): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
