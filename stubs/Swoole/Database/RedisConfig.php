<?php

declare(strict_types=1);

namespace Swoole\Database;


class RedisConfig {

    
    public function getHost(): \string {
        return "";
    }

    
    public function withHost(\string $host): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    
    public function getPort(): \int {
        return 0;
    }

    
    public function withPort(\int $port): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    
    public function getTimeout(): \float {
        return 0.0;
    }

    
    public function withTimeout(\float $timeout): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    
    public function getReserved(): \string {
        return "";
    }

    
    public function withReserved(\string $reserved): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    
    public function getRetryInterval(): \int {
        return 0;
    }

    
    public function withRetryInterval(\int $retry_interval): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    
    public function getReadTimeout(): \float {
        return 0.0;
    }

    
    public function withReadTimeout(\float $read_timeout): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    
    public function getAuth(): \string {
        return "";
    }

    
    public function withAuth(\string $auth): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    
    public function getDbIndex(): \int {
        return 0;
    }

    
    public function withDbIndex(\int $dbIndex): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    /**
     * Add a configurable option.
     */
    public function withOption(\int $option, $value): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    /**
     * Add/override configurable options.
     *
     * @param array<int, mixed> $options
     */
    public function setOptions(\array $options): \Swoole\Database\RedisConfig {
        return class_exists(\Swoole\Database\RedisConfig::class) ? \Swoole\Database\RedisConfig::class : \stdClass::class;
    }

    /**
     * Get configurable options.
     *
     * @return array<int, mixed>
     */
    public function getOptions(): \array {
        return [];
    }
}
