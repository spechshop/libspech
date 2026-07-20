<?php

declare(strict_types=1);

namespace Swoole\Database;


class PDOConfig {
    public const DRIVER_MYSQL = 'mysql';

    
    public function getDriver(): \string {
        return "";
    }

    
    public function withDriver(\string $driver): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    
    public function getHost(): \string {
        return "";
    }

    
    public function withHost(\string $host): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    
    public function getPort(): \int {
        return 0;
    }

    
    public function hasUnixSocket(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getUnixSocket(): \string {
        return "";
    }

    
    public function withUnixSocket(\string $unixSocket): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    
    public function withPort(\int $port): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    
    public function getDbname(): \string {
        return "";
    }

    
    public function withDbname(\string $dbname): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    
    public function getCharset(): \string {
        return "";
    }

    
    public function withCharset(\string $charset): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    
    public function getUsername(): \string {
        return "";
    }

    
    public function withUsername(\string $username): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    
    public function getPassword(): \string {
        return "";
    }

    
    public function withPassword(\string $password): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    
    public function getOptions(): \array {
        return [];
    }

    
    public function withOptions(\array $options): \Swoole\Database\PDOConfig {
        return class_exists(\Swoole\Database\PDOConfig::class) ? \Swoole\Database\PDOConfig::class : \stdClass::class;
    }

    /**
     * Returns the list of available drivers
     *
     * @return string[]
     */
    public static function getAvailableDrivers(): \array {
        return [];
    }
}
