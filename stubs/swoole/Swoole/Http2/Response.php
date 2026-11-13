<?php

declare(strict_types=1);

namespace Swoole\Http2;

class Response
{
    public $streamId = 0;
    public $errCode = 0;
    public $statusCode = 0;
    public $pipeline = false;
    public $headers = NULL;
    public $set_cookie_headers = NULL;
    public $cookies = NULL;
    public $data = NULL;
}
