<?php

declare(strict_types=1);

namespace Swoole\FastCGI\Record;

/**
 * Params request record
 */
class Params {

    /**
     * Constructs a param request
     *
     * @phpstan-param array<string, string> $values
     */
    public function __construct(\array $values) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * Returns an associative list of parameters
     *
     * @phpstan-return array<string, string>
     */
    public function getValues(): \array {
        return [];
    }
}
