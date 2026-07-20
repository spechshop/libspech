<?php

declare(strict_types=1);

namespace Swoole\FastCGI\Record;

/**
 * The Web server sends a FCGI_BEGIN_REQUEST record to start a request.
 */
class BeginRequest {

    
    public function __construct(\int $role = 3, \int $flags = 0, \string $reserved = '') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * Returns the role
     *
     * The role component sets the role the Web server expects the application to play.
     * The currently-defined roles are:
     *   FCGI_RESPONDER
     *   FCGI_AUTHORIZER
     *   FCGI_FILTER
     */
    public function getRole(): \int {
        return 0;
    }

    /**
     * Returns the flags
     *
     * The flags component contains a bit that controls connection shutdown.
     *
     * flags & FCGI_KEEP_CONN:
     *   If zero, the application closes the connection after responding to this request.
     *   If not zero, the application does not close the connection after responding to this request;
     *   the Web server retains responsibility for the connection.
     */
    public function getFlags(): \int {
        return 0;
    }
}
