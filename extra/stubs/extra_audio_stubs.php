<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED stubs for `extra/*` examples.
 * Run: php extra/tools/01_generate_stubs.php
 */

function decodePcmaToPcm(string $input): string {}

function decodePcmuToPcm(string $input): string {}

function encodePcmToPcma(string $input): string {}

function encodePcmToPcmu(string $input): string {}

function decodeL16ToPcm(string $input): string {}

function encodePcmToL16(string $input): string {}

function mixAudioChannels(array $channels, int $sample_rate): string {}

function resampler(string $pcm, int $src_rate, int $dst_rate, bool $stereo = false): string {}

class opusChannel
{
    public function __construct(int $sample_rate, int $channels) {}
    public function encode(string $pcm_data, int $pcm_rate): string {}
    public function decode(string $encoded_data, int $pcm_rate_out): string {}
    public function resample(string $pcm_data, int $src_rate, int $dst_rate): string {}
    public function setBitrate(int $value) {}
    public function setVBR(bool $enable) {}
    public function setComplexity(int $value) {}
    public function setDTX(bool $enable) {}
    public function setSignalVoice(bool $enable) {}
    public function reset() {}
    public function enhanceVoiceClarity(string $pcm_data, float $intensity): string {}
    public function spatialStereoEnhance(string $pcm_data, float $width, float $depth): string {}
    public function destroy() {}
}
