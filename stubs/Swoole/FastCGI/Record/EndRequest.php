<?php

declare(strict_types=1);

namespace Swoole\FastCGI\Record;

/**
 * The application sends a FCGI_END_REQUEST record to terminate a request, either because the application
 * has processed the request or because the application has rejected the request.
 */
class EndRequest {

    
    public function __construct(\int $protocolStatus = 0, \int $appStatus = 0, \string $reserved = '') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * Returns app status
     *
     * The appStatus component is an application-level status code. Each role documents its usage of appStatus.
     */
    public function getAppStatus(): \int {
        return 0;
    }

    /**
     * Returns the protocol status
     *
     * The possible protocolStatus values are:
     *   FCGI_REQUEST_COMPLETE: normal end of request.
     *   FCGI_CANT_MPX_CONN: rejecting a new request.
     *      This happens when a Web server sends concurrent requests over one connection to an application that is
     *      designed to process one request at a time per connection.
     *   FCGI_OVERLOADED: rejecting a new request.
     *      This happens when the application runs out of some resource, e.g. database connections.
     *   FCGI_UNKNOWN_ROLE: rejecting a new request.
     *      This happens when the Web server has specified a role that is unknown to the application.
     */
    public function getProtocolStatus(): \int {
        return 0;
    }
}
