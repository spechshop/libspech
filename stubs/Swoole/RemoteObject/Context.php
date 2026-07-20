<?php

declare(strict_types=1);

namespace Swoole\RemoteObject;


class Context {

    
    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function end(\array $data) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getHandler(): \string {
        return "";
    }

    
    public function getParam(\string $name): \string {
        return "";
    }

    
    public function getDataParam(\string $name): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getCoroutineId(): \int {
        return 0;
    }

    
    public function getClientId(): \string {
        return "";
    }
}
