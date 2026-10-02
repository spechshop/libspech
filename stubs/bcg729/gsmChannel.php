<?php

declare(strict_types=1);

class gsmChannel
{

    public function __construct() {}

    public function encode(string $input): string|false {}

    public function decode(string $input): string|false {}

    public function info(): array {}

    public function close(): bool {}
}
