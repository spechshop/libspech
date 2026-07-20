<?php

declare(strict_types=1);

namespace Swoole;

/**
 * FastCGI constants.
 */
class FastCGI {
    public const HEADER_LEN = 8;
    public const HEADER_FORMAT = 'Cversion/Ctype/nrequestId/ncontentLength/CpaddingLength/Creserved';
    public const MAX_CONTENT_LENGTH = 65535;
    public const VERSION_1 = 1;
    public const BEGIN_REQUEST = 1;
    public const ABORT_REQUEST = 2;
    public const END_REQUEST = 3;
    public const PARAMS = 4;
    public const STDIN = 5;
    public const STDOUT = 6;
    public const STDERR = 7;
    public const DATA = 8;
    public const GET_VALUES = 9;
    public const GET_VALUES_RESULT = 10;
    public const UNKNOWN_TYPE = 11;
    public const DEFAULT_REQUEST_ID = 1;
    public const KEEP_CONN = 1;
    public const RESPONDER = 1;
    public const AUTHORIZER = 2;
    public const FILTER = 3;
    public const REQUEST_COMPLETE = 0;
    public const CANT_MPX_CONN = 1;
    public const OVERLOADED = 2;
    public const UNKNOWN_ROLE = 3;
}
