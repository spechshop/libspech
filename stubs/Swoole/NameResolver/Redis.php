<?php

declare(strict_types=1);

namespace Swoole\NameResolver;


class Redis {

    
    public function __construct($url, $prefix = 'swoole:service:') {
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
}
