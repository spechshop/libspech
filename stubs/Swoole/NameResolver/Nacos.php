<?php

declare(strict_types=1);

namespace Swoole\NameResolver;


class Nacos {

    /**
     * @throws Coroutine\Http\Client\Exception|Exception
     */
    public function join(\string $name, \string $ip, \int $port, \array $options = []): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Coroutine\Http\Client\Exception|Exception
     */
    public function leave(\string $name, \string $ip, \int $port): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @throws Coroutine\Http\Client\Exception|Exception|\Swoole\Exception
     */
    public function getCluster(\string $name): \Swoole\NameResolver\Cluster {
        return class_exists(\Swoole\NameResolver\Cluster::class) ? \Swoole\NameResolver\Cluster::class : \stdClass::class;
    }
}
