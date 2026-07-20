<?php

declare(strict_types=1);

namespace Swoole\FastCGI;


class Response {

    /**
     * @param array<Stdout|Stderr|EndRequest> $records
     */
    public function __construct(\array $records) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
