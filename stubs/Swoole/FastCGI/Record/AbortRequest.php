<?php

declare(strict_types=1);

namespace Swoole\FastCGI\Record;

/**
 * The Web server sends a FCGI_ABORT_REQUEST record to abort a request
 */
class AbortRequest {

    
    public function __construct(\int $requestId) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
