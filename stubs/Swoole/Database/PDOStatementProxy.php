<?php

declare(strict_types=1);

namespace Swoole\Database;

/**
 * The proxy class for PHP class PDOStatement.
 *
 * @see https://www.php.net/PDOStatement The PDOStatement class
 */
class PDOStatementProxy {

    
    public function __construct(\PDOStatement $object, \Swoole\Database\PDOProxy $parent) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __call(\string $name, \array $arguments) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setAttribute(\int $attribute, $value): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * Set the default fetch mode for this statement.
     *
     * @see https://www.php.net/manual/en/pdostatement.setfetchmode.php
     */
    public function setFetchMode(\int $mode, $params = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function bindParam($parameter, &$variable, $data_type = 2, $length = 0, $driver_options = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function bindColumn($column, &$param, $type = NULL, $maxlen = NULL, $driverdata = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function bindValue($parameter, $value, $data_type = 2): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
