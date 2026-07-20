<?php

declare(strict_types=1);

namespace Swoole;


class RemoteObject {

    
    public function __construct($coroutineId, $clientId) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __destruct() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Exception
     */
    public function __call(\string $method, \array $args) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Exception
     */
    public function __get(\string $property) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __set(\string $property, $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __unserialize(\array $data) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __serialize(): \array {
        return [];
    }

    
    public function __toString(): \string {
        return "";
    }

    
    public function __invoke($args = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function call(\Swoole\RemoteObject\Client $client, \string $fn, \array $args) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getObjectId(): \int {
        return 0;
    }

    /**
     * @throws Exception
     */
    public static function create(\Swoole\RemoteObject\Client $client, \string $class, \array $args): \Swoole\RemoteObject {
        return class_exists(\Swoole\RemoteObject::class) ? \Swoole\RemoteObject::class : \stdClass::class;
    }

    /**
     * This method is only used on the server side.
     */
    public static function marshal(\int $objectId, \int $ownerCoroutineId, \string $clientId): \Swoole\RemoteObject {
        return class_exists(\Swoole\RemoteObject::class) ? \Swoole\RemoteObject::class : \stdClass::class;
    }

    
    public function offsetGet($offset): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Exception
     */
    public function offsetSet($offset, $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Exception
     */
    public function offsetUnset($offset) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetExists($offset): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function current(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function next() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function key(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function valid(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function rewind() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }
}
