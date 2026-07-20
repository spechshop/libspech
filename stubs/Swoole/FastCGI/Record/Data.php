<?php

declare(strict_types=1);

namespace Swoole\FastCGI\Record;

/**
 * Data binary stream
 *
 * FCGI_DATA is a second stream record type used to send additional data to the application.
 */
class Data {

    
    public function __construct(\string $contentData) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
