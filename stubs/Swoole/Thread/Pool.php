<?php

declare(strict_types=1);

namespace Swoole\Thread;

/**
 * @since 6.0.0-beta
 */
class Pool {

    
    public function __construct(\string $runnableClass, \int $threadNum) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function withArguments($arguments = NULL): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function withAutoloader(\string $autoloader): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function withClassDefinitionFile(\string $classDefinitionFile): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * @throws \ReflectionException
     */
    public function start() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function shutdown() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
