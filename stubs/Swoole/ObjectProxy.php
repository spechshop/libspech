<?php

declare(strict_types=1);

namespace Swoole;


class ObjectProxy {

    
    public function __construct(\object $object) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __getObject() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __get(\string $name) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __set(\string $name, $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __isset($name) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __unset(\string $name) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __call(\string $name, \array $arguments) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __invoke($arguments = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
