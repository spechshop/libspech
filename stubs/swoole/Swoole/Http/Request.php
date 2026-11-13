<?php

declare(strict_types=1);

namespace Swoole\Http;

class Request
{
    public $fd = 0;
    public $streamId = 0;
    public $header = NULL;
    public $server = NULL;
    public $cookie = NULL;
    public $get = NULL;
    public $files = NULL;
    public $post = NULL;
    public $tmpfiles = NULL;

    public function getContent(): string|false {}

    public function rawContent(): string|false {}

    public function getData(): string|false {}

    public static function create(array $options = array (
)): \Swoole\Http\Request {}

    public function parse(string $data): int|false {}

    public function isCompleted(): bool {}

    public function getMethod(): string|false {}
}
