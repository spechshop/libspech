<?php

declare(strict_types=1);

namespace Swoole\NameResolver;


class Cluster {

    /**
     * @throws Exception
     */
    public function add(\string $host, \int $port, \int $weight = 100) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return false|string
     */
    public function pop() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }
}
