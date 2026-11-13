<?php

declare(strict_types=1);

namespace Swoole\Http;

class Response
{
    public $fd = 0;
    public $socket = NULL;
    public $header = NULL;
    public $cookie = NULL;
    public $trailer = NULL;

    public function initHeader(): bool {}

    public function isWritable(): bool {}

    public function cookie(\Swoole\Http\Cookie|string $name_or_object, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool {}

    public function setCookie(\Swoole\Http\Cookie|string $name_or_object, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool {}

    public function rawcookie(\Swoole\Http\Cookie|string $name_or_object, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool {}

    public function setRawCookie(\Swoole\Http\Cookie|string $name_or_object, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool {}

    public function status(int $http_code, string $reason = ''): bool {}

    public function setStatusCode(int $http_code, string $reason = ''): bool {}

    public function header(string $key, array|string $value, bool $format = true): bool {}

    public function setHeader(string $key, array|string $value, bool $format = true): bool {}

    public function trailer(string $key, string $value): bool {}

    public function ping(string $data = ''): bool {}

    public function goaway(int $error_code = 0, string $debug_data = ''): bool {}

    public function write(string $content): bool {}

    public function end(?string $content = NULL): bool {}

    public function sendfile(string $filename, int $offset = 0, int $length = 0): bool {}

    public function redirect(string $location, int $http_code = 302): bool {}

    public function detach(): bool {}

    public static function create(object|array|int $server = -1, int $fd = -1): \Swoole\Http\Response|false {}

    public function upgrade(): bool {}

    public function push(\Swoole\WebSocket\Frame|string $data, int $opcode = 1, int $flags = 1): bool {}

    public function recv(float $timeout = 0): \Swoole\WebSocket\Frame|string|false {}

    public function close(): bool {}

    public function disconnect(int $code = 1000, string $reason = ''): bool {}
}
