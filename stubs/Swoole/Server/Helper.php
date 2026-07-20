<?php

declare(strict_types=1);

namespace Swoole\Server;


class Helper {
    public const STATS_TIMER_INTERVAL_TIME = 1000;
    public const GLOBAL_OPTIONS = [
  'debug_mode' => true,
  'trace_flags' => true,
  'log_file' => true,
  'log_level' => true,
  'log_date_format' => true,
  'log_date_with_microseconds' => true,
  'log_rotation' => true,
  'display_errors' => true,
  'dns_server' => true,
  'socket_dns_timeout' => true,
  'socket_connect_timeout' => true,
  'socket_write_timeout' => true,
  'socket_send_timeout' => true,
  'socket_read_timeout' => true,
  'socket_recv_timeout' => true,
  'socket_buffer_size' => true,
  'socket_timeout' => true,
  'http2_header_table_size' => true,
  'http2_enable_push' => true,
  'http2_max_concurrent_streams' => true,
  'http2_init_window_size' => true,
  'http2_max_frame_size' => true,
  'http2_max_header_list_size' => true,];
    public const SERVER_OPTIONS = [
  'chroot' => true,
  'user' => true,
  'group' => true,
  'daemonize' => true,
  'pid_file' => true,
  'reactor_num' => true,
  'single_thread' => true,
  'worker_num' => true,
  'max_wait_time' => true,
  'max_queued_bytes' => true,
  'max_concurrency' => true,
  'worker_max_concurrency' => true,
  'enable_coroutine' => true,
  'send_timeout' => true,
  'dispatch_mode' => true,
  'send_yield' => true,
  'dispatch_func' => true,
  'discard_timeout_request' => true,
  'enable_unsafe_event' => true,
  'enable_delay_receive' => true,
  'enable_reuse_port' => true,
  'task_use_object' => true,
  'task_object' => true,
  'event_object' => true,
  'task_enable_coroutine' => true,
  'task_worker_num' => true,
  'task_ipc_mode' => true,
  'task_tmpdir' => true,
  'task_max_request' => true,
  'task_max_request_grace' => true,
  'max_connection' => true,
  'max_conn' => true,
  'start_session_id' => true,
  'heartbeat_check_interval' => true,
  'heartbeat_idle_time' => true,
  'max_request' => true,
  'max_request_grace' => true,
  'reload_async' => true,
  'open_cpu_affinity' => true,
  'cpu_affinity_ignore' => true,
  'http_parse_cookie' => true,
  'http_parse_post' => true,
  'http_parse_files' => true,
  'http_compression' => true,
  'http_compression_level' => true,
  'compression_level' => true,
  'http_gzip_level' => true,
  'http_compression_min_length' => true,
  'compression_min_length' => true,
  'websocket_compression' => true,
  'upload_tmp_dir' => true,
  'upload_max_filesize' => true,
  'enable_static_handler' => true,
  'document_root' => true,
  'http_autoindex' => true,
  'http_index_files' => true,
  'http_compression_types' => true,
  'compression_types' => true,
  'static_handler_locations' => true,
  'input_buffer_size' => true,
  'buffer_input_size' => true,
  'output_buffer_size' => true,
  'buffer_output_size' => true,
  'message_queue_key' => true,
  'bootstrap' => true,
  'init_arguments' => true,
  'url_rewrite_rules' => true,];
    public const PORT_OPTIONS = [
  'ssl_cert_file' => true,
  'ssl_key_file' => true,
  'backlog' => true,
  'socket_buffer_size' => true,
  'kernel_socket_recv_buffer_size' => true,
  'kernel_socket_send_buffer_size' => true,
  'heartbeat_idle_time' => true,
  'buffer_high_watermark' => true,
  'buffer_low_watermark' => true,
  'open_tcp_nodelay' => true,
  'tcp_defer_accept' => true,
  'open_tcp_keepalive' => true,
  'open_eof_check' => true,
  'open_eof_split' => true,
  'package_eof' => true,
  'open_http_protocol' => true,
  'open_websocket_protocol' => true,
  'websocket_subprotocol' => true,
  'open_websocket_close_frame' => true,
  'open_websocket_ping_frame' => true,
  'open_websocket_pong_frame' => true,
  'open_http2_protocol' => true,
  'open_mqtt_protocol' => true,
  'open_redis_protocol' => true,
  'max_idle_time' => true,
  'tcp_keepidle' => true,
  'tcp_keepinterval' => true,
  'tcp_keepcount' => true,
  'tcp_user_timeout' => true,
  'tcp_fastopen' => true,
  'open_length_check' => true,
  'package_length_type' => true,
  'package_length_offset' => true,
  'package_body_offset' => true,
  'package_body_start' => true,
  'package_length_func' => true,
  'package_max_length' => true,
  'ssl_compress' => true,
  'ssl_protocols' => true,
  'ssl_verify_peer' => true,
  'ssl_allow_self_signed' => true,
  'ssl_client_cert_file' => true,
  'ssl_cafile' => true,
  'ssl_capath' => true,
  'ssl_verify_depth' => true,
  'ssl_prefer_server_ciphers' => true,
  'ssl_ciphers' => true,
  'ssl_ecdh_curve' => true,
  'ssl_dhparam' => true,
  'ssl_sni_certs' => true,];
    public const AIO_OPTIONS = [
  'aio_core_worker_num' => true,
  'aio_worker_num' => true,
  'aio_max_wait_time' => true,
  'aio_max_idle_time' => true,
  'iouring_entries' => true,
  'iouring_workers' => true,
  'iouring_flag' => true,
  'enable_signalfd' => true,
  'wait_signal' => true,
  'dns_cache_refresh_time' => true,
  'thread_num' => true,
  'min_thread_num' => true,
  'max_thread_num' => true,
  'socket_dontwait' => true,
  'dns_lookup_random' => true,
  'use_async_resolver' => true,
  'enable_coroutine' => true,];
    public const COROUTINE_OPTIONS = [
  'max_coro_num' => true,
  'max_coroutine' => true,
  'enable_deadlock_check' => true,
  'hook_flags' => true,
  'enable_preemptive_scheduler' => true,
  'c_stack_size' => true,
  'stack_size' => true,
  'name_resolver' => true,
  'dns_cache_expire' => true,
  'dns_cache_capacity' => true,];
    public const HELPER_OPTIONS = [
  'stats_file' => true,
  'stats_timer_interval' => true,
  'admin_server' => true,];

    
    public static function checkOptions(\array $input_options) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onBeforeStart(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onBeforeShutdown(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onWorkerStart(\Swoole\Server $server, \int $workerId) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onWorkerExit(\Swoole\Server $server, \int $workerId) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onWorkerStop(\Swoole\Server $server, \int $workerId) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onStart(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onShutdown(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onBeforeReload(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onAfterReload(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onManagerStart(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onManagerStop(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function onWorkerError(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
