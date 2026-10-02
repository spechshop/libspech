<?php

declare(strict_types=1);

class PCMAnalyzer
{

    public function __construct(int $sampleRate = 8000, int $frameDurationMs = 20) {}

    public function analyze(string $pcm): array {}
}
