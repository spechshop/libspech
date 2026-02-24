<?php

namespace Swoole;

/**
 * Mock de Swoole\Coroutine para testes
 */
class Coroutine
{
    public static function create(callable $func, ...$params)
    {
        // Mock: executa diretamente (sem async)
        return $func(...$params);
    }
}
