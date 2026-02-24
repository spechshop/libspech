<?php

namespace Swoole\Coroutine;

/**
 * Mock de Swoole\Coroutine\Channel para testes
 */
class Channel
{
    private array $data = [];
    private int $size;

    public function __construct(int $size = 1)
    {
        $this->size = $size;
    }

    public function push($data, float $timeout = -1): bool
    {
        if (count($this->data) >= $this->size) {
            return false;
        }
        $this->data[] = $data;
        return true;
    }

    public function pop(float $timeout = -1)
    {
        if (empty($this->data)) {
            return null;
        }
        return array_shift($this->data);
    }

    public function close(): bool
    {
        $this->data = [];
        return true;
    }

    public function length(): int
    {
        return count($this->data);
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }
}
