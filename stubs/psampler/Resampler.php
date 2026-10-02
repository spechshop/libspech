<?php

declare(strict_types=1);

class Resampler
{

    public function __construct(?int $srcRate = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */, ?int $dstRate = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */) {}

    public function reset() {}

    public function sample(string $pcm, ?int $srcRate = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */, ?int $dstRate = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */): string {}

    public function process(string $pcm): string {}

    public function returnEmpty(): string|false {}
}
