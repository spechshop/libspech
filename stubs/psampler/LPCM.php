<?php

declare(strict_types=1);

class LPCM
{

    public function __construct(int $channels, int $bitDepth, bool $isBigEndian = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */) {}

    public function encodeMono(array $samples): string {}

    public function decodeMono(string $pcmData): array {}

    public function encodeStereo(array $leftSamples, array $rightSamples): string {}

    public function decodeStereo(string $pcmData): array {}
}
