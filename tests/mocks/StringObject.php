<?php

namespace libspech\Rtp;

/**
 * Mock de StringObject para testes de mixPcmArray
 */
class StringObject
{
    private string $data = '';

    public function __construct(string $initial = '')
    {
        $this->data = $initial;
    }

    public function append(string $str): void
    {
        $this->data .= $str;
    }

    public function toString(): string
    {
        return $this->data;
    }
}
