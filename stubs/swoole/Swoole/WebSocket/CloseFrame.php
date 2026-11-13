<?php

declare(strict_types=1);

namespace Swoole\WebSocket;

class CloseFrame extends \Swoole\WebSocket\Frame
{
    public $opcode = 8;
    public $code = 1000;
    public $reason = '';
}
