<?php

/**
 * Bootstrap para testes do rtpChannel
 * Mock de extensões PHP necessárias
 */

// Mock da classe bcg729Channel se não existir
if (!class_exists('bcg729Channel')) {
    class bcg729Channel
    {
        public function encode(string $data): string
        {
            return str_repeat("\x00", 10); // Mock: retorna 10 bytes de G.729
        }

        public function decode(string $data): string
        {
            return str_repeat("\x00", 160); // Mock: retorna 160 samples
        }
    }
}

// Mock da classe opusChannel se não existir
if (!class_exists('opusChannel')) {
    class opusChannel
    {
        private int $sampleRate;
        private int $channels;
        private int $bitrate = 24000;

        public function __construct(int $sampleRate, int $channels)
        {
            $this->sampleRate = $sampleRate;
            $this->channels = $channels;
        }

        public function setBitrate(int $bitrate): void
        {
            $this->bitrate = $bitrate;
        }

        public function setSignalVoice(bool $voice): void
        {
            // Mock: não faz nada
        }

        public function setDTX(bool $dtx): void
        {
            // Mock: não faz nada
        }

        public function setVBR(bool $vbr): void
        {
            // Mock: não faz nada
        }

        public function setComplexity(int $complexity): void
        {
            // Mock: não faz nada
        }

        public function encode(string $pcm): string
        {
            return str_repeat("\x00", 120); // Mock: retorna pacote opus
        }

        public function decode(string $opus): string
        {
            return str_repeat("\x00", 960); // Mock: retorna PCM
        }

        public function resample(string $pcm, int $fromRate, int $toRate): string
        {
            return str_repeat("\x00", 960); // Mock: retorna PCM resampleado
        }
    }
}

// Mock da classe StringObject para mixPcmArray
if (!class_exists('libspech\Rtp\StringObject')) {
    require_once __DIR__ . '/mocks/StringObject.php';
}

// Carrega mocks do Swoole se as classes não existirem
if (!class_exists('Swoole\Coroutine\Socket')) {
    require_once __DIR__ . '/mocks/SwooleSocket.php';
}

if (!class_exists('Swoole\Coroutine\Channel')) {
    require_once __DIR__ . '/mocks/SwooleChannel.php';
}

if (!class_exists('Swoole\Coroutine')) {
    require_once __DIR__ . '/mocks/SwooleCoroutine.php';
}

// Carrega o autoloader do projeto
require_once __DIR__ . '/../plugins/autoloader.php';

echo "✓ Bootstrap carregado com sucesso\n";
echo "✓ Mocks de extensões criados\n";
echo "✓ Autoloader do projeto carregado\n\n";
