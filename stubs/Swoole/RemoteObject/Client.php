<?php

declare(strict_types=1);

namespace Swoole\RemoteObject;


class Client {

    
    public function __construct(\string $host = '127.0.0.1', \int $port = 9567, \array $options = []) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function create(\string $class, $args = NULL): \Swoole\RemoteObject {
        return class_exists(\Swoole\RemoteObject::class) ? \Swoole\RemoteObject::class : \stdClass::class;
    }

    
    public function call(\string $fn, $args = NULL): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Exception
     */
    public static function getInstance(\string $clientId): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function getId(): \string {
        return "";
    }

    
    public function execute(\string $path, \array $array) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function ping(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
