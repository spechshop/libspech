<?php

declare(strict_types=1);

namespace Swoole\FastCGI\Record;

/**
 * Record for unknown queries
 *
 * The set of management record types is likely to grow in future versions of this protocol.
 * To provide for this evolution, the protocol includes the FCGI_UNKNOWN_TYPE management record.
 * When an application receives a management record whose type T it does not understand, the application responds
 * with {FCGI_UNKNOWN_TYPE, 0, {T}}.
 */
class UnknownType {

    
    public function __construct(\int $type, \string $reserved = '') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * Returns the unrecognized type
     */
    public function getUnrecognizedType(): \int {
        return 0;
    }

    /**
     * {@inheritdoc}
     * @param static $self
     */
    public static function unpackPayload(\Swoole\FastCGI\Record $self, \string $binaryData) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
