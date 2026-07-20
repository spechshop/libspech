<?php

declare(strict_types=1);

namespace Swoole;


class NameResolver {

    
    public function __construct($url, $prefix = 'swoole_service_') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function join(\string $name, \string $ip, \int $port, \array $options = []): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function leave(\string $name, \string $ip, \int $port): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getCluster(\string $name): \Swoole\NameResolver\Cluster {
        return class_exists(\Swoole\NameResolver\Cluster::class) ? \Swoole\NameResolver\Cluster::class : \stdClass::class;
    }

    
    public function withFilter(\callable $fn): \Swoole\NameResolver {
        return class_exists(\Swoole\NameResolver::class) ? \Swoole\NameResolver::class : \stdClass::class;
    }

    
    public function getFilter() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function hasFilter(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * return string: final result, non-empty string must be a valid IP address,
     * and an empty string indicates name lookup failed, and lookup operation will not continue.
     * return Cluster: has multiple nodes and failover is possible
     * return false or null: try another name resolver
     * @return Cluster|false|string|null
     */
    public function lookup(\string $name) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }
}
