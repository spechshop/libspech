<?php

declare(strict_types=1);

namespace Swoole\FastCGI;

/**
 * Utility class to simplify parsing of FastCGI protocol data.
 */
class FrameParser {

    /**
     * Checks if the buffer contains a valid frame to parse
     */
    public static function hasFrame(\string $binaryBuffer): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * Parses a frame from the binary buffer
     *
     * @return Record One of the corresponding FastCGI record
     */
    public static function parseFrame(\string &$binaryBuffer): \Swoole\FastCGI\Record {
        return class_exists(\Swoole\FastCGI\Record::class) ? \Swoole\FastCGI\Record::class : \stdClass::class;
    }
}
