<?php

declare(strict_types=1);

namespace Swoole\FastCGI;

/**
 * FastCGI record.
 */
class Record {

    /**
     * Returns the binary message representation of record
     */
    public function __toString(): \string {
        return "";
    }

    /**
     * Unpacks the message from the binary data buffer
     */
    public static function unpack(\string $binaryData): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * Sets the content data and adjusts the length fields
     *
     * @return static
     */
    public function setContentData(\string $data): \Swoole\FastCGI\Record {
        return class_exists(\Swoole\FastCGI\Record::class) ? \Swoole\FastCGI\Record::class : \stdClass::class;
    }

    /**
     * Returns the context data from the record
     */
    public function getContentData(): \string {
        return "";
    }

    /**
     * Returns the version of record
     */
    public function getVersion(): \int {
        return 0;
    }

    /**
     * Returns record type
     */
    public function getType(): \int {
        return 0;
    }

    /**
     * Returns request ID
     */
    public function getRequestId(): \int {
        return 0;
    }

    /**
     * Sets request ID
     *
     * There should be only one unique ID for all active requests,
     * use random number or preferably resetting auto-increment.
     *
     * @return static
     */
    public function setRequestId(\int $requestId): \Swoole\FastCGI\Record {
        return class_exists(\Swoole\FastCGI\Record::class) ? \Swoole\FastCGI\Record::class : \stdClass::class;
    }

    /**
     * Returns the size of content length
     */
    public function getContentLength(): \int {
        return 0;
    }

    /**
     * Returns the size of padding length
     */
    public function getPaddingLength(): \int {
        return 0;
    }
}
