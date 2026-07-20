<?php

declare(strict_types=1);

namespace Swoole\FastCGI\Record;

/**
 * Stdin binary stream
 *
 * FCGI_STDIN is a stream record type used in sending arbitrary data from the Web server to the application
 */
class Stdin {

    
    public function __construct(\string $contentData) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
