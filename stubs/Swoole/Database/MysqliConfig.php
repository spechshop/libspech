<?php

declare(strict_types=1);

namespace Swoole\Database;


class MysqliConfig {

    
    public function getHost(): \string {
        return "";
    }

    
    public function withHost(\string $host): \Swoole\Database\MysqliConfig {
        return class_exists(\Swoole\Database\MysqliConfig::class) ? \Swoole\Database\MysqliConfig::class : \stdClass::class;
    }

    
    public function getPort(): \int {
        return 0;
    }

    
    public function getUnixSocket(): \string {
        return "";
    }

    
    public function withUnixSocket(\string $unixSocket): \Swoole\Database\MysqliConfig {
        return class_exists(\Swoole\Database\MysqliConfig::class) ? \Swoole\Database\MysqliConfig::class : \stdClass::class;
    }

    
    public function withPort(\int $port): \Swoole\Database\MysqliConfig {
        return class_exists(\Swoole\Database\MysqliConfig::class) ? \Swoole\Database\MysqliConfig::class : \stdClass::class;
    }

    
    public function getDbname(): \string {
        return "";
    }

    
    public function withDbname(\string $dbname): \Swoole\Database\MysqliConfig {
        return class_exists(\Swoole\Database\MysqliConfig::class) ? \Swoole\Database\MysqliConfig::class : \stdClass::class;
    }

    
    public function getCharset(): \string {
        return "";
    }

    
    public function withCharset(\string $charset): \Swoole\Database\MysqliConfig {
        return class_exists(\Swoole\Database\MysqliConfig::class) ? \Swoole\Database\MysqliConfig::class : \stdClass::class;
    }

    
    public function getUsername(): \string {
        return "";
    }

    
    public function withUsername(\string $username): \Swoole\Database\MysqliConfig {
        return class_exists(\Swoole\Database\MysqliConfig::class) ? \Swoole\Database\MysqliConfig::class : \stdClass::class;
    }

    
    public function getPassword(): \string {
        return "";
    }

    
    public function withPassword(\string $password): \Swoole\Database\MysqliConfig {
        return class_exists(\Swoole\Database\MysqliConfig::class) ? \Swoole\Database\MysqliConfig::class : \stdClass::class;
    }

    
    public function getOptions(): \array {
        return [];
    }

    
    public function withOptions(\array $options): \Swoole\Database\MysqliConfig {
        return class_exists(\Swoole\Database\MysqliConfig::class) ? \Swoole\Database\MysqliConfig::class : \stdClass::class;
    }
}
