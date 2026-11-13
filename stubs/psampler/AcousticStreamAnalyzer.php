<?php

declare(strict_types=1);

final class AcousticStreamAnalyzer
{

    public function __construct(int $sampleRate = 8000, int $frameDurationMs = 20) {}

    public function on(string $event, callable $callback): \AcousticStreamAnalyzer {}

    public function push(string $pcm): void {}

    public function state(): array {}

    public function reset(): void {}
}
