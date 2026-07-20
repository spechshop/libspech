<?php

declare(strict_types=1);

namespace Swoole\Server;


class Admin {
    public const SIZE_OF_ZVAL = 16;
    public const SIZE_OF_ZEND_STRING = 32;
    public const SIZE_OF_ZEND_OBJECT = 56;
    public const SIZE_OF_ZEND_ARRAY = 56;

    
    public static function init(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function getAccessToken(): \string {
        return "";
    }

    
    public static function start(\Swoole\Server $server) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return false|string
     */
    public static function handlerGetResources(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return false|string
     */
    public static function handlerGetWorkerInfo(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return false|string
     */
    public static function handlerCloseSession(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return false|string
     */
    public static function handlerGetTimerList(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return false|string
     */
    public static function handlerGetCoroutineList(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetObjects(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetClassInfo(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetFunctionInfo(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetObjectByHandle(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetVersionInfo(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetDefinedFunctions(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetDeclaredClasses(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetServerMemoryUsage(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetServerCpuUsage(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function handlerGetStaticPropertyValue(\Swoole\Server $server, \string $msg) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
