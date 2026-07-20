<?php

declare(strict_types=1);

namespace Swoole\Database;

/**
 * @method \mysqli __getObject()
 */
class MysqliProxy {
    public const IO_METHOD_REGEX = '/^autocommit|begin_transaction|change_user|close|commit|kill|multi_query|ping|prepare|query|real_connect|real_query|reap_async_query|refresh|release_savepoint|rollback|savepoint|select_db|send_query|set_charset|ssl_set$/i';
    public const IO_ERRORS = [
  0 => 2002,
  1 => 2006,
  2 => 2013,];

    
    public function __construct(\callable $constructor) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __call(\string $name, \array $arguments) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getRound(): \int {
        return 0;
    }

    
    public function reconnect() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function options(\int $option, $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set_opt(\int $option, $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function set_charset(\string $charset): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function change_user(\string $user, \string $password, \string $database): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
