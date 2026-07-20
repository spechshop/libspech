<?php

declare(strict_types=1);


    function swoole_version(): \string {
        return "";
    }


    function swoole_cpu_num(): \int {
        return 0;
    }


    function swoole_last_error(): \int {
        return 0;
    }


    function swoole_async_dns_lookup_coro(string $domain_name, float $timeout = 60, int $type = 2) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_async_set(array $settings): \bool {
        return false;
    }


    function swoole_coroutine_create(callable $func, $params = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_coroutine_defer(callable $callback): \void {
        return;
    }


    function swoole_coroutine_socketpair(int $domain, int $type, int $protocol) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_test_kernel_coroutine(int $count = 100, float $sleep_time = 1.0): \void {
        return;
    }


    function swoole_client_select(array &$read, array &$write, array &$except, float $timeout = 0.5) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_select(array &$read, array &$write, array &$except, float $timeout = 0.5) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_set_process_name(string $process_name): \bool {
        return false;
    }


    function swoole_get_local_ip(int $family = 2): \array {
        return [];
    }


    function swoole_get_local_mac(): \array {
        return [];
    }


    function swoole_strerror(int $errno, int $error_type = 0): \string {
        return "";
    }


    function swoole_errno(): \int {
        return 0;
    }


    function swoole_clear_error(): \void {
        return;
    }


    function swoole_error_log(int $level, string $msg): \void {
        return;
    }


    function swoole_error_log_ex(int $level, int $error, string $msg): \void {
        return;
    }


    function swoole_ignore_error(int $error): \void {
        return;
    }


    function swoole_hashcode(string $data, int $type = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_mime_type_add(string $suffix, string $mime_type): \bool {
        return false;
    }


    function swoole_mime_type_set(string $suffix, string $mime_type): \void {
        return;
    }


    function swoole_mime_type_delete(string $suffix): \bool {
        return false;
    }


    function swoole_mime_type_get(string $filename): \string {
        return "";
    }


    function swoole_get_mime_type(string $filename): \string {
        return "";
    }


    function swoole_mime_type_exists(string $filename): \bool {
        return false;
    }


    function swoole_mime_type_list(): \array {
        return [];
    }


    function swoole_clear_dns_cache(): \void {
        return;
    }


    function swoole_substr_unserialize(string $str, int $offset, int $length = 0, array $options = []) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_substr_json_decode(string $str, int $offset, int $length = 0, bool $associative = false, int $depth = 512, int $flags = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_internal_call_user_shutdown_begin(): \bool {
        return false;
    }


    function swoole_implicit_fn(string $fn, $args = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_get_objects() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_get_vm_status(): \array {
        return [];
    }


    function swoole_get_object_by_handle(int $handle) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_name_resolver_lookup(string $name, Swoole\NameResolver\Context $ctx): \string {
        return "";
    }


    function swoole_name_resolver_add(Swoole\NameResolver $ns): \bool {
        return false;
    }


    function swoole_name_resolver_remove(Swoole\NameResolver $ns): \bool {
        return false;
    }


    function swoole_call_array_method($args = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_call_string_method($args = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_call_stream_method($args = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_array_search(array $array, $value, bool $strict = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_array_contains(array $array, $needle, bool $strict = false): \bool {
        return false;
    }


    function swoole_array_join(array $array, string $separator): \string {
        return "";
    }


    function swoole_array_key_exists(array $array, resource|string|int|float|bool|null $key): \bool {
        return false;
    }


    function swoole_array_map(array $array, callable $callback, array $arrays = NULL): \array {
        return [];
    }


    function swoole_str_split(string $string, string $delimiter, int $limit = 9223372036854775807): \array {
        return [];
    }


    function swoole_parse_str(string $string): \array {
        return [];
    }


    function swoole_hash(string $data, string $algo, bool $binary = false, array $options = []): \string {
        return "";
    }


    function swoole_typed_array(string $typeDef, array $initArray = NULL): \array {
        return [];
    }


    function swoole_array_is_typed(array $array, string $typeDef = ''): \bool {
        return false;
    }


    function swoole_str_is_empty(string $string): \bool {
        return false;
    }


    function swoole_array_is_empty(array $array): \bool {
        return false;
    }


    function swoole_str_match(string $string, string $pattern, int $flags = 0, int $offset = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_str_match_all(string $string, string $pattern, int $flags = 0, int $offset = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_str_json_decode(string $string, int $depth = 512, int $flags = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_str_json_decode_to_object(string $string, int $depth = 512, int $flags = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_str_replace(string $subject, array|string $search, array|string $replace): \string {
        return "";
    }


    function swoole_str_ireplace(string $subject, array|string $search, array|string $replace): \string {
        return "";
    }


    function swoole_array_replace_str(array $subjects, array|string $search, array|string $replace): \array {
        return [];
    }


    function swoole_array_ireplace_str(array $subjects, array|string $search, array|string $replace): \array {
        return [];
    }


    function swoole_tracer_leak_detect(int $threshold = 64): \void {
        return;
    }


    function swoole_tracer_prof_begin(array $options = NULL): \bool {
        return false;
    }


    function swoole_tracer_prof_end(string $output_file): \bool {
        return false;
    }


    function go(callable $func) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function defer(callable $callback): \void {
        return;
    }


    function typed_array(string $typeDef, array $initArray = NULL): \array {
        return [];
    }


    function swoole_event_add($fd, callable $read_callback = NULL, callable $write_callback = NULL, int $events = 512) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_event_del($fd): \bool {
        return false;
    }


    function swoole_event_set($fd, callable $read_callback = NULL, callable $write_callback = NULL, int $events = 0): \bool {
        return false;
    }


    function swoole_event_wait(): \void {
        return;
    }


    function swoole_event_isset($fd, int $events = 1536): \bool {
        return false;
    }


    function swoole_event_dispatch(): \bool {
        return false;
    }


    function swoole_event_defer(callable $callback): \bool {
        return false;
    }


    function swoole_event_cycle(callable $callback, bool $before = false): \bool {
        return false;
    }


    function swoole_event_write($fd, string $data): \bool {
        return false;
    }


    function swoole_event_exit(): \void {
        return;
    }


    function swoole_event_rshutdown(): \void {
        return;
    }


    function swoole_timer_after(int $ms, callable $callback) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_timer_tick(int $ms, callable $callback) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_timer_info(int $timer_id): \array {
        return [];
    }


    function swoole_timer_list(): \Swoole\Timer\Iterator {
        return class_exists(\Swoole\Timer\Iterator::class) ? \Swoole\Timer\Iterator::class : \stdClass::class;
    }


    function swoole_timer_exists(int $timer_id): \bool {
        return false;
    }


    function swoole_timer_stats(): \array {
        return [];
    }


    function swoole_timer_clear(int $timer_id): \bool {
        return false;
    }


    function swoole_timer_clear_all(): \bool {
        return false;
    }


    function swoole_native_curl_close(CurlHandle $handle): \void {
        return;
    }


    function swoole_native_curl_copy_handle(CurlHandle $handle) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_native_curl_errno(CurlHandle $handle): \int {
        return 0;
    }


    function swoole_native_curl_error(CurlHandle $handle): \string {
        return "";
    }


    function swoole_native_curl_escape(CurlHandle $handle, string $string) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_native_curl_unescape(CurlHandle $handle, string $string) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_native_curl_multi_setopt(CurlMultiHandle $multi_handle, int $option, $value): \bool {
        return false;
    }


    function swoole_native_curl_exec(CurlHandle $handle) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_native_curl_getinfo(CurlHandle $handle, int $option = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_native_curl_init(string $url = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_native_curl_upkeep(CurlHandle $handle): \bool {
        return false;
    }


    function swoole_native_curl_multi_add_handle(CurlMultiHandle $multi_handle, CurlHandle $handle): \int {
        return 0;
    }


    function swoole_native_curl_multi_close(CurlMultiHandle $multi_handle): \void {
        return;
    }


    function swoole_native_curl_multi_errno(CurlMultiHandle $multi_handle): \int {
        return 0;
    }


    function swoole_native_curl_multi_exec(CurlMultiHandle $multi_handle, &$still_running): \int {
        return 0;
    }


    function swoole_native_curl_multi_getcontent(CurlHandle $handle): \string {
        return "";
    }


    function swoole_native_curl_multi_info_read(CurlMultiHandle $multi_handle, &$queued_messages = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_native_curl_multi_init(): \CurlMultiHandle {
        return class_exists(\CurlMultiHandle::class) ? \CurlMultiHandle::class : \stdClass::class;
    }


    function swoole_native_curl_multi_remove_handle(CurlMultiHandle $multi_handle, CurlHandle $handle): \int {
        return 0;
    }


    function swoole_native_curl_multi_select(CurlMultiHandle $multi_handle, float $timeout = 1.0): \int {
        return 0;
    }


    function swoole_native_curl_pause(CurlHandle $handle, int $flags): \int {
        return 0;
    }


    function swoole_native_curl_reset(CurlHandle $handle): \void {
        return;
    }


    function swoole_native_curl_setopt_array(CurlHandle $handle, array $options): \bool {
        return false;
    }


    function swoole_native_curl_setopt(CurlHandle $handle, int $option, $value): \bool {
        return false;
    }


    function swoole_exec(string $command, &$output = NULL, &$returnVar = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_shell_exec(string $cmd) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_curl_init(string $url = ''): \Swoole\Curl\Handler {
        return class_exists(\Swoole\Curl\Handler::class) ? \Swoole\Curl\Handler::class : \stdClass::class;
    }


    function swoole_curl_setopt(Swoole\Curl\Handler $obj, int $opt, $value): \bool {
        return false;
    }


    function swoole_curl_setopt_array(Swoole\Curl\Handler $obj, $array): \bool {
        return false;
    }


    function swoole_curl_exec(Swoole\Curl\Handler $obj) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_curl_getinfo(Swoole\Curl\Handler $obj, int $opt = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_curl_errno(Swoole\Curl\Handler $obj): \int {
        return 0;
    }


    function swoole_curl_error(Swoole\Curl\Handler $obj): \string {
        return "";
    }


    function swoole_curl_reset(Swoole\Curl\Handler $obj) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_curl_close(Swoole\Curl\Handler $obj): \void {
        return;
    }


    function swoole_curl_multi_getcontent(Swoole\Curl\Handler $obj) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_create(int $domain, int $type, int $protocol) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_connect(Swoole\Coroutine\Socket $socket, string $address, int $port = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_read(Swoole\Coroutine\Socket $socket, int $length, int $type = 2) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_write(Swoole\Coroutine\Socket $socket, string $buffer, int $length = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_send(Swoole\Coroutine\Socket $socket, string $buffer, int $length, int $flags) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_recv(Swoole\Coroutine\Socket $socket, &$buffer, int $length, int $flags) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_sendto(Swoole\Coroutine\Socket $socket, string $buffer, int $length, int $flags, string $addr, int $port = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_recvfrom(Swoole\Coroutine\Socket $socket, &$buffer, int $length, int $flags, &$name, &$port = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_bind(Swoole\Coroutine\Socket $socket, string $address, int $port = 0): \bool {
        return false;
    }


    function swoole_socket_listen(Swoole\Coroutine\Socket $socket, int $backlog = 0): \bool {
        return false;
    }


    function swoole_socket_create_listen(int $port, int $backlog = 128) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_accept(Swoole\Coroutine\Socket $socket) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_getpeername(Swoole\Coroutine\Socket $socket, &$address, &$port = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_getsockname(Swoole\Coroutine\Socket $socket, &$address, &$port = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_set_option(Swoole\Coroutine\Socket $socket, int $level, int $optname, $optval): \bool {
        return false;
    }


    function swoole_socket_setopt(Swoole\Coroutine\Socket $socket, int $level, int $optname, $optval): \bool {
        return false;
    }


    function swoole_socket_get_option(Swoole\Coroutine\Socket $socket, int $level, int $optname) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_getopt(Swoole\Coroutine\Socket $socket, int $level, int $optname) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_shutdown(Swoole\Coroutine\Socket $socket, int $how = 2): \bool {
        return false;
    }


    function swoole_socket_close(Swoole\Coroutine\Socket $socket) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_clear_error(Swoole\Coroutine\Socket $socket = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_last_error(Swoole\Coroutine\Socket $socket = NULL): \int {
        return 0;
    }


    function swoole_socket_set_block(Swoole\Coroutine\Socket $socket) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_set_nonblock(Swoole\Coroutine\Socket $socket) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_socket_create_pair(int $domain, int $type, int $protocol, array &$pair) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

/**
 * @since 5.0.0
 */
    function swoole_socket_import_stream($stream) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_gethostbynamel(string $domain) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_mail(string $to, string $subject, string $message, array $headers = []): \bool {
        return false;
    }


    function swoole_checkdnsrr(string $hostname, string $type = 'MX'): \bool {
        return false;
    }


    function swoole_dns_check_record(string $hostname, string $type = 'MX'): \bool {
        return false;
    }


    function swoole_real_getmxrr(string $hostname, array $hosts = NULL, array $weights = NULL): \array {
        return [];
    }


    function swoole_getmxrr(string $hostname, array &$hosts, array &$weights = NULL): \bool {
        return false;
    }


    function swoole_dns_get_mx(string $hostname, array &$hosts, array &$weights = NULL): \bool {
        return false;
    }


    function swoole_real_dns_get_record(string $hostname, int $type, array $authoritative_name_servers = NULL, array $additional_records = NULL, bool $raw = false): \array {
        return [];
    }


    function swoole_dns_get_record(string $hostname, int $type = 268435456, array &$authoritative_name_servers = NULL, array &$additional_records = NULL, bool $raw = false) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_gethostbyaddr(string $ip): \string {
        return "";
    }

/**
 * @param array<string, mixed> $options
 */
    function swoole_library_set_options(array $options): \void {
        return;
    }


    function swoole_library_get_options(): \array {
        return [];
    }


    function swoole_library_set_option(string $key, $value): \void {
        return;
    }


    function swoole_library_get_option(string $key) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_string(string $string = ''): \Swoole\StringObject {
        return class_exists(\Swoole\StringObject::class) ? \Swoole\StringObject::class : \stdClass::class;
    }


    function swoole_mbstring(string $string = ''): \Swoole\MultibyteStringObject {
        return class_exists(\Swoole\MultibyteStringObject::class) ? \Swoole\MultibyteStringObject::class : \stdClass::class;
    }


    function swoole_array(array $array = []): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }


    function swoole_table(int $size, string $fields): \Swoole\Table {
        return class_exists(\Swoole\Table::class) ? \Swoole\Table::class : \stdClass::class;
    }


    function swoole_array_list($arrray = NULL): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }


    function swoole_array_default_value(array $array, $key, $default_value = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function swoole_is_in_container(): \bool {
        return false;
    }


    function swoole_container_cpu_num(): \int {
        return 0;
    }


    function swoole_init_default_remote_object_server(): \void {
        return;
    }


    function swoole_get_default_remote_object_client(): \Swoole\RemoteObject\Client {
        return class_exists(\Swoole\RemoteObject\Client::class) ? \Swoole\RemoteObject\Client::class : \stdClass::class;
    }


    function _string(string $string = ''): \Swoole\StringObject {
        return class_exists(\Swoole\StringObject::class) ? \Swoole\StringObject::class : \stdClass::class;
    }


    function _mbstring(string $string = ''): \Swoole\MultibyteStringObject {
        return class_exists(\Swoole\MultibyteStringObject::class) ? \Swoole\MultibyteStringObject::class : \stdClass::class;
    }


    function _array(array $array = []): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }


    function safeexport($v) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }


    function writestubfile($namespace, $className, $code): \void {
        return;
    }


    function generatefunctionstubs(string $ext): \void {
        return;
    }

/**
 * @param string $ext
 * @return string[]|void
 */
    function getexplode(string $ext) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

/**
 * @param ReflectionParameter $p
 * @param string $s
 * @param array $params
 * @return array
 */
    function getarr(ReflectionParameter $p, string $s, array $params): \array {
        return [];
    }


    function generateextensionconstants(string $ext): \void {
        return;
    }

/**
 * @param string $ext
 * @return void
 */
    function extracted(string $ext): \void {
        return;
    }


    function generateclassstubs(array $allowFilters): \void {
        return;
    }

/**
 * @param array $allowFilters
 * @return string[]
 */
    function getexplode1(array $allowFilters): \array {
        return [];
    }


    function liststubfolders($dir = '/home/lotus/projetos/libspech/stubs'): \void {
        return;
    }

