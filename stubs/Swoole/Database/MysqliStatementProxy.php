<?php

declare(strict_types=1);

namespace Swoole\Database;


class MysqliStatementProxy {
    public const IO_METHOD_REGEX = '/^close|execute|fetch|prepare$/i';

    
    public function __construct(\mysqli_stmt $object, \string $queryString, \Swoole\Database\MysqliProxy $parent) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __call(\string $name, \array $arguments) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function attr_set($attr, $mode): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function bind_param($types, &$arguments = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function bind_result(&$arguments = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
