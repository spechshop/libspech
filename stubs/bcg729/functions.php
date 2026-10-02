<?php

declare(strict_types=1);

function decodePcmaToPcm(string $input): string {}

function decodePcmuToPcm(string $input): string {}

function encodePcmToPcma(string $input): string {}

function encodePcmToPcmu(string $input): string {}

function decodeL16ToPcm(string $input): string {}

function encodePcmToL16(string $input): string {}

function mixAudioChannels(array $channels, int $sample_rate = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */): string {}

function pcmLeToBe(string $input): string {}

function resampler(string $input, int $src_rate, int $dst_rate, bool $to_be = \__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */): string {}
