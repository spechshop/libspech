<?php

declare(strict_types=1);

function swoole_version(): string {}

function swoole_cpu_num(): int {}

function swoole_last_error(): int {}

function swoole_async_dns_lookup_coro(string $domain_name, float $timeout = 60, int $type = 2): string|false {}

function swoole_async_set(array $settings): bool {}

function swoole_coroutine_create(callable $func, mixed ...$params): int|false {}

function swoole_coroutine_defer(callable $callback): void {}

function swoole_coroutine_socketpair(int $domain, int $type, int $protocol): array|false {}

function swoole_test_kernel_coroutine(int $count = 100, float $sleep_time = 1.0): void {}

function swoole_client_select(?array &$read, ?array &$write, ?array &$except, ?float $timeout = 0.5): int|false {}

function swoole_select(?array &$read, ?array &$write, ?array &$except, ?float $timeout = 0.5): int|false {}

function swoole_set_process_name(string $process_name): bool {}

function swoole_get_local_ip(int $family = 2): array {}

function swoole_get_local_mac(): array {}

function swoole_strerror(int $errno, int $error_type = 0): string {}

function swoole_errno(): int {}

function swoole_clear_error(): void {}

function swoole_error_log(int $level, string $msg): void {}

function swoole_error_log_ex(int $level, int $error, string $msg): void {}

function swoole_ignore_error(int $error): void {}

function swoole_hashcode(string $data, int $type = 0): int|false {}

function swoole_mime_type_add(string $suffix, string $mime_type): bool {}

function swoole_mime_type_set(string $suffix, string $mime_type): void {}

function swoole_mime_type_delete(string $suffix): bool {}

function swoole_mime_type_get(string $filename): string {}

function swoole_get_mime_type(string $filename): string {}

function swoole_mime_type_exists(string $filename): bool {}

function swoole_mime_type_list(): array {}

function swoole_clear_dns_cache(): void {}

function swoole_substr_unserialize(string $str, int $offset, int $length = 0, array $options = array (
)): mixed {}

function swoole_substr_json_decode(string $str, int $offset, int $length = 0, bool $associative = false, int $depth = 512, int $flags = 0): mixed {}

function swoole_internal_call_user_shutdown_begin(): bool {}

function swoole_implicit_fn(string $fn, mixed $args = NULL): mixed {}

function swoole_get_objects(): array|false {}

function swoole_get_vm_status(): array {}

function swoole_get_object_by_handle(int $handle): object|false {}

function swoole_name_resolver_lookup(string $name, \Swoole\NameResolver\Context $ctx): string {}

function swoole_name_resolver_add(\Swoole\NameResolver $ns): bool {}

function swoole_name_resolver_remove(\Swoole\NameResolver $ns): bool {}

function swoole_tracer_leak_detect(int $threshold = 64): void {}

function swoole_tracer_prof_begin(?array $options = NULL): bool {}

function swoole_tracer_prof_end(string $output_file): bool {}

function go(callable $func): int|false {}

function defer(callable $callback): void {}

function swoole_event_add(mixed $fd, ?callable $read_callback = NULL, ?callable $write_callback = NULL, int $events = 512): int|false {}

function swoole_event_del(mixed $fd): bool {}

function swoole_event_set(mixed $fd, ?callable $read_callback = NULL, ?callable $write_callback = NULL, int $events = 0): bool {}

function swoole_event_wait(): void {}

function swoole_event_isset(mixed $fd, int $events = 1536): bool {}

function swoole_event_dispatch(): bool {}

function swoole_event_defer(callable $callback): bool {}

function swoole_event_cycle(?callable $callback, bool $before = false): bool {}

function swoole_event_write(mixed $fd, string $data): bool {}

function swoole_event_exit(): void {}

function swoole_event_rshutdown(): void {}

function swoole_timer_after(int $ms, callable $callback): int|false {}

function swoole_timer_tick(int $ms, callable $callback): int|false {}

function swoole_timer_info(int $timer_id): ?array {}

function swoole_timer_list(): \Swoole\Timer\Iterator {}

function swoole_timer_exists(int $timer_id): bool {}

function swoole_timer_stats(): array {}

function swoole_timer_clear(int $timer_id): bool {}

function swoole_timer_clear_all(): bool {}

function swoole_native_curl_close(\CurlHandle $handle): void {}

function swoole_native_curl_copy_handle(\CurlHandle $handle): \CurlHandle|false {}

function swoole_native_curl_errno(\CurlHandle $handle): int {}

function swoole_native_curl_error(\CurlHandle $handle): string {}

function swoole_native_curl_escape(\CurlHandle $handle, string $string): string|false {}

function swoole_native_curl_unescape(\CurlHandle $handle, string $string): string|false {}

function swoole_native_curl_multi_setopt(\CurlMultiHandle $multi_handle, int $option, mixed $value): bool {}

function swoole_native_curl_exec(\CurlHandle $handle): string|bool {}

function swoole_native_curl_getinfo(\CurlHandle $handle, ?int $option = NULL): mixed {}

function swoole_native_curl_init(?string $url = NULL): \CurlHandle|false {}

function swoole_native_curl_upkeep(\CurlHandle $handle): bool {}

function swoole_native_curl_multi_add_handle(\CurlMultiHandle $multi_handle, \CurlHandle $handle): int {}

function swoole_native_curl_multi_close(\CurlMultiHandle $multi_handle): void {}

function swoole_native_curl_multi_errno(\CurlMultiHandle $multi_handle): int {}

function swoole_native_curl_multi_exec(\CurlMultiHandle $multi_handle, &$still_running): int {}

function swoole_native_curl_multi_getcontent(\CurlHandle $handle): ?string {}

function swoole_native_curl_multi_info_read(\CurlMultiHandle $multi_handle, &$queued_messages = NULL): array|false {}

function swoole_native_curl_multi_init(): \CurlMultiHandle {}

function swoole_native_curl_multi_remove_handle(\CurlMultiHandle $multi_handle, \CurlHandle $handle): int {}

function swoole_native_curl_multi_select(\CurlMultiHandle $multi_handle, float $timeout = 1.0): int {}

function swoole_native_curl_pause(\CurlHandle $handle, int $flags): int {}

function swoole_native_curl_reset(\CurlHandle $handle): void {}

function swoole_native_curl_setopt_array(\CurlHandle $handle, array $options): bool {}

function swoole_native_curl_setopt(\CurlHandle $handle, int $option, mixed $value): bool {}
