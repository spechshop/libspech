<?php

declare(strict_types=1);

namespace Swoole\FastCGI;


class Request {

    
    public function __toString(): \string {
        return "";
    }

    
    public function getKeepConn(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function withKeepConn(\bool $keepConn): \Swoole\FastCGI\Request {
        return class_exists(\Swoole\FastCGI\Request::class) ? \Swoole\FastCGI\Request::class : \stdClass::class;
    }
}
