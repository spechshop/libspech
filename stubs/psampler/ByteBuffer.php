<?php

declare(strict_types=1);

final class ByteBuffer
{

    public function __construct(int $initialCapacity = 4096) {}

    public function append(string $data): void {}

    public function appendBuffer(object $source, int $offset = 0, ?int $length = NULL): void {}

    public static function supportsNativeBuffer(object $source): bool {}

    public function length(): int {}

    public function has(int $bytes): bool {}

    public function pop(int $bytes): string {}

    public function peek(int $bytes): string {}

    public function discard(int $bytes): void {}

    public function clear(): void {}

    public function capacity(): int {}
}
