<?php

declare(strict_types=1);

namespace Swoole\FastCGI\Record;

/**
 * Stderr binary stream
 *
 * FCGI_STDERR is a stream record for sending arbitrary data from the application to the Web server
 */
class Stderr {

    
    public function __construct(\string $contentData) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
