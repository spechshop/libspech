<?php

namespace Swoole\Coroutine;

/**
 * Mock de Swoole\Coroutine\Socket para testes
 */
class Socket
{
    public function __construct(int $domain, int $type, int $protocol = 0)
    {
        // Mock: não faz nada
    }

    public function recvfrom(array &$peer, float $timeout = -1)
    {
        return false;
    }

    public function sendto(string $addr, int $port, string $data)
    {
        return strlen($data);
    }

    public function close(): bool
    {
        return true;
    }

    public function isClosed(): bool
    {
        return false;
    }
}
