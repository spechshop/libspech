<?php

declare(strict_types=1);

namespace Swoole\Http2;

class Request
{
    public $path = '/';
    public $method = 'GET';
    public $headers = NULL;
    public $cookies = NULL;
    public $data = '';
    public $pipeline = false;
    public $usePipelineRead = false;
}
