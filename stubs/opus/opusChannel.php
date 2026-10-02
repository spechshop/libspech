<?php

declare(strict_types=1);

class opusChannel
{

    public function __construct(int $sample_rate = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */, int $channels = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */) {}

    public function encode(string $pcm_data, ?int $pcm_rate = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */): string {}

    public function decode(string $encoded_data, ?int $pcm_rate_out = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */): string {}

    public function resample(string $pcm_data, int $src_rate, int $dst_rate): string {}

    public function setBitrate(int $value) {}

    public function setVBR(bool $enable) {}

    public function setComplexity(int $value) {}

    public function setDTX(bool $enable) {}

    public function setSignalVoice(bool $enable) {}

    public function reset(): void {}

    public function enhanceVoiceClarity(string $pcm_data, ?float $intensity = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */): string {}

    public function spatialStereoEnhance(string $pcm_data, ?float $width = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */, ?float $depth = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */): string {}

    public function monoToStereo(string $pcm_data): string {}

    public function stereoToMono(string $pcm_data): string {}

    public function hasLibsoxr(): bool {}

    public function getInfo(): array {}

    public function destroy(): void {}
}
