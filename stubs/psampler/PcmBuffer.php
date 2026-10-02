<?php

declare(strict_types=1);

final class PcmBuffer
{

    public function __construct(int $sampleRate, int $channels) {}

    public function append(string $pcm): void {}

    public function size(): int {}

    public function capacity(): int {}

    public function sampleRate(): int {}

    public function channels(): int {}

    public function clear(): void {}

    public function flush(): \PcmBuffer {}

    public function reset(int $sampleRate, int $channels): void {}

    public function toString(): string {}

    public function toMono(): \PcmBuffer {}

    public function toStereo(): \PcmBuffer {}

    public function resample(int $sampleRate): \PcmBuffer {}

    public function canInvoke(string $operation): bool {}

    public function invoke(string $operation, mixed ...$args): mixed {}
}
