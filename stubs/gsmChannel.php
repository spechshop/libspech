<?php

declare(strict_types=1);


class gsmChannel {

    public function __construct() {
    }

    public function encode(string $input): string|false {
        return false;
    }

    public function decode(string $input): string|false {
        return false;
    }

    /**
     * @return array{
     *   encoder_initialized: bool,
     *   decoder_initialized: bool,
     *   sample_rate: int,
     *   samples_per_frame: int,
     *   pcm_bytes_per_frame: int,
     *   gsm_bytes_per_frame: int,
     *   frame_duration_ms: int
     * }
     */
    public function info(): array {
        return [];
    }

    public function close(): bool {
        return true;
    }
}
