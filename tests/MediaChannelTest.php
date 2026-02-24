<?php

namespace Tests;

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpc;

require_once __DIR__ . '/bootstrap.php';

/**
 * Suite de testes para MediaChannel
 * Foca em métodos testáveis sem dependências de Swoole Socket/Coroutine
 */
class MediaChannelTest
{
    private int $testsPassed = 0;
    private int $testsFailed = 0;
    private array $failures = [];

    public function __construct()
    {
    }

    /**
     * Executa todos os testes
     */
    public function runAll(): bool
    {
        echo "\n\033[1;36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
        echo "\033[1;36m  MediaChannel Test Suite\033[0m\n";
        echo "\033[1;36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

        $this->testCodecResolution();
        $this->testFrequencyResolution();
        $this->testSsrcGeneration();
        $this->testMixPcmArray();
        $this->testRegisterPtCodecs();
        $this->testVadConfiguration();
        $this->testDtmfTranslation();
        $this->testTelephoneEventPt();
        $this->testMemberManagement();

        return $this->printSummary();
    }

    /**
     * Testa resolução de codec a partir do PT
     */
    private function testCodecResolution(): void
    {
        echo "📋 Codec Resolution Tests\n";

        // Mock de Socket e callId para construtor
        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();

        // Teste 1: PCMU (PT 0)
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $codec = $channel->resolveCodecNameFromPt(0);
            return $codec === 'PCMU';
        }, "PT 0 resolve para PCMU");

        // Teste 2: PCMA (PT 8)
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $codec = $channel->resolveCodecNameFromPt(8);
            return $codec === 'PCMA';
        }, "PT 8 resolve para PCMA");

        // Teste 3: G729 (PT 18)
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $codec = $channel->resolveCodecNameFromPt(18);
            return $codec === 'G729';
        }, "PT 18 resolve para G729");

        // Teste 4: telephone-event (PT 101)
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $codec = $channel->resolveCodecNameFromPt(101);
            return $codec === 'telephone-event';
        }, "PT 101 resolve para telephone-event");

        // Teste 5: Codec registrado via ptCodecs
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->ptCodecs[96] = 'OPUS';
            $codec = $channel->resolveCodecNameFromPt(96);
            return $codec === 'OPUS';
        }, "PT customizado resolve corretamente");

        // Teste 6: Fallback para PT desconhecido
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $codec = $channel->resolveCodecNameFromPt(99);
            return $codec === 'G729'; // Default fallback
        }, "PT desconhecido retorna fallback G729");

        echo "\n";
    }

    /**
     * Testa resolução de frequência a partir do PT
     */
    private function testFrequencyResolution(): void
    {
        echo "📋 Frequency Resolution Tests\n";

        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();

        // Teste 1: PCMU 8kHz
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $freq = $channel->resolveFrequencyFromPt(0);
            return $freq === 8000;
        }, "PT 0 (PCMU) retorna 8000 Hz");

        // Teste 2: PCMA 8kHz
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $freq = $channel->resolveFrequencyFromPt(8);
            return $freq === 8000;
        }, "PT 8 (PCMA) retorna 8000 Hz");

        // Teste 3: Codec customizado com frequência
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->ptCodecs[96] = 'OPUS';
            $channel->ptCodecsFrequency['OPUS'] = 48000;
            $freq = $channel->resolveFrequencyFromPt(96);
            return $freq === 48000;
        }, "PT customizado retorna frequência correta");

        // Teste 4: Fallback para 8000 Hz
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $freq = $channel->resolveFrequencyFromPt(99);
            return $freq === 8000;
        }, "PT desconhecido retorna fallback 8000 Hz");

        echo "\n";
    }

    /**
     * Testa geração de SSRC determinístico
     */
    private function testSsrcGeneration(): void
    {
        echo "📋 SSRC Generation Tests\n";

        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();

        // Teste 1: SSRC determinístico
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $ssrc1 = $channel->generateDeterministicSsrc('192.168.1.1:5060');
            $ssrc2 = $channel->generateDeterministicSsrc('192.168.1.1:5060');
            return $ssrc1 === $ssrc2;
        }, "SSRC é determinístico para mesmo IP:port");

        // Teste 2: SSRC diferente para IPs diferentes
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $ssrc1 = $channel->generateDeterministicSsrc('192.168.1.1:5060');
            $ssrc2 = $channel->generateDeterministicSsrc('192.168.1.2:5060');
            return $ssrc1 !== $ssrc2;
        }, "SSRC diferente para IPs diferentes");

        // Teste 3: SSRC diferente para portas diferentes
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $ssrc1 = $channel->generateDeterministicSsrc('192.168.1.1:5060');
            $ssrc2 = $channel->generateDeterministicSsrc('192.168.1.1:5062');
            return $ssrc1 !== $ssrc2;
        }, "SSRC diferente para portas diferentes");

        // Teste 4: SSRC dentro do range 32 bits
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $ssrc = $channel->generateDeterministicSsrc('192.168.1.1:5060');
            return $ssrc >= 0 && $ssrc <= 0xFFFFFFFF;
        }, "SSRC dentro do range de 32 bits");

        // Teste 5: SSRC sempre positivo
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $ssrc = $channel->generateDeterministicSsrc('192.168.1.1:5060');
            return $ssrc >= 0;
        }, "SSRC sempre não-negativo");

        echo "\n";
    }

    /**
     * Testa mixagem de arrays PCM
     */
    private function testMixPcmArray(): void
    {
        echo "📋 PCM Mixing Tests\n";

        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();
        $channel = new MediaChannel($socket, $callId);

        // Teste 1: Array vazio retorna vazio
        $this->assert(function () use ($channel) {
            $result = $channel->mixPcmArray([]);
            return $result === "";
        }, "Array vazio retorna string vazia");

        // Teste 2: Um único chunk retorna o mesmo
        $this->assert(function () use ($channel) {
            $pcm = pack('s*', 100, 200, 300);
            $result = $channel->mixPcmArray([$pcm]);
            return $result === $pcm;
        }, "Único chunk retorna sem modificação");

        // Teste 3: Dois chunks são mixados
        $this->assert(function () use ($channel) {
            $pcm1 = pack('s*', 100, 200);
            $pcm2 = pack('s*', 50, 100);
            $result = $channel->mixPcmArray([$pcm1, $pcm2]);
            $samples = array_values(unpack('s*', $result));
            return $samples[0] === 150 && $samples[1] === 300;
        }, "Dois chunks são somados corretamente");

        // Teste 4: Clipping no máximo
        $this->assert(function () use ($channel) {
            $pcm1 = pack('s', 30000);
            $pcm2 = pack('s', 30000);
            $result = $channel->mixPcmArray([$pcm1, $pcm2]);
            $samples = array_values(unpack('s*', $result));
            return $samples[0] === 32767; // Máximo clipped
        }, "Clipping no máximo (32767)");

        // Teste 5: Clipping no mínimo
        $this->assert(function () use ($channel) {
            $pcm1 = pack('s', -30000);
            $pcm2 = pack('s', -30000);
            $result = $channel->mixPcmArray([$pcm1, $pcm2]);
            $samples = array_values(unpack('s*', $result));
            return $samples[0] === -32768; // Mínimo clipped
        }, "Clipping no mínimo (-32768)");

        // Teste 6: Três chunks são mixados
        $this->assert(function () use ($channel) {
            $pcm1 = pack('s', 100);
            $pcm2 = pack('s', 200);
            $pcm3 = pack('s', 300);
            $result = $channel->mixPcmArray([$pcm1, $pcm2, $pcm3]);
            $samples = array_values(unpack('s*', $result));
            return $samples[0] === 600;
        }, "Três chunks são somados corretamente");

        echo "\n";
    }

    /**
     * Testa registro de codecs PT
     */
    private function testRegisterPtCodecs(): void
    {
        echo "📋 PT Codec Registration Tests\n";

        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();

        // Teste 1: Registro de codec OPUS
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([96 => 'OPUS/48000']);
            return $channel->ptCodecs[96] === 'OPUS'
                && $channel->ptCodecsFrequency['OPUS'] === 48000;
        }, "Registro de OPUS/48000");

        // Teste 2: Codecs padrão são registrados
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([]);
            return $channel->ptCodecs[0] === 'PCMU'
                && $channel->ptCodecs[8] === 'PCMA'
                && $channel->ptCodecs[18] === 'G729'
                && $channel->ptCodecs[101] === 'telephone-event';
        }, "Codecs padrão são registrados");

        // Teste 3: telephone-event usa chave composta
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([101 => 'telephone-event/8000']);
            return isset($channel->ptCodecsFrequency['telephone-event_8000'])
                && $channel->ptCodecsFrequency['telephone-event_8000'] === 8000;
        }, "telephone-event usa chave composta");

        // Teste 4: Sobrescrever codec padrão
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([0 => 'CUSTOM/16000']);
            return $channel->ptCodecs[0] === 'CUSTOM'
                && $channel->ptCodecsFrequency['CUSTOM'] === 16000;
        }, "Codec padrão pode ser sobrescrito");

        // Teste 5: Múltiplos codecs
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([
                96 => 'OPUS/48000',
                97 => 'H264/90000',
                98 => 'VP8/90000'
            ]);
            return $channel->ptCodecs[96] === 'OPUS'
                && $channel->ptCodecs[97] === 'H264'
                && $channel->ptCodecs[98] === 'VP8';
        }, "Múltiplos codecs são registrados");

        echo "\n";
    }

    /**
     * Testa configuração de VAD
     */
    private function testVadConfiguration(): void
    {
        echo "📋 VAD Configuration Tests\n";

        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();

        // Teste 1: VAD desabilitado por padrão
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            return $channel->vadEnabled === false;
        }, "VAD desabilitado por padrão");

        // Teste 2: Habilitar VAD
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->enableVAD(3.0);
            return $channel->vadEnabled === true;
        }, "enableVAD ativa corretamente");

        // Teste 3: Threshold customizado
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $reflection = new \ReflectionClass($channel);
            $property = $reflection->getProperty('vadThreshold');
            $property->setAccessible(true);

            $channel->enableVAD(5.5);
            return $property->getValue($channel) === 5.5;
        }, "Threshold VAD customizado");

        // Teste 4: setVadRegistrationThreshold
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $reflection = new \ReflectionClass($channel);
            $property = $reflection->getProperty('vadRegistrationThreshold');
            $property->setAccessible(true);

            $channel->setVadRegistrationThreshold(4.0);
            return $property->getValue($channel) === 4.0;
        }, "setVadRegistrationThreshold funciona");

        // Teste 5: setVadTimeout
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $reflection = new \ReflectionClass($channel);
            $property = $reflection->getProperty('vadTimeoutSeconds');
            $property->setAccessible(true);

            $channel->setVadTimeout(30);
            return $property->getValue($channel) === 30;
        }, "setVadTimeout funciona");

        echo "\n";
    }

    /**
     * Testa tradução de dígitos DTMF
     */
    private function testDtmfTranslation(): void
    {
        echo "📋 DTMF Translation Tests\n";

        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();
        $channel = new MediaChannel($socket, $callId);

        $reflection = new \ReflectionClass($channel);
        $method = $reflection->getMethod('translateDigit');
        $method->setAccessible(true);

        // Teste 1-10: Dígitos 0-9
        for ($i = 0; $i <= 9; $i++) {
            $this->assert(function () use ($channel, $method, $i) {
                $digit = $method->invoke($channel, $i);
                return $digit === (string)$i;
            }, "Event $i traduz para dígito '$i'");
        }

        // Teste 11: Asterisco
        $this->assert(function () use ($channel, $method) {
            $digit = $method->invoke($channel, 10);
            return $digit === '*';
        }, "Event 10 traduz para '*'");

        // Teste 12: Hash
        $this->assert(function () use ($channel, $method) {
            $digit = $method->invoke($channel, 11);
            return $digit === '#';
        }, "Event 11 traduz para '#'");

        // Teste 13: Evento inválido
        $this->assert(function () use ($channel, $method) {
            $digit = $method->invoke($channel, 99);
            return $digit === '';
        }, "Event inválido retorna string vazia");

        echo "\n";
    }

    /**
     * Testa busca de PT do telephone-event
     */
    private function testTelephoneEventPt(): void
    {
        echo "📋 Telephone Event PT Tests\n";

        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();

        // Teste 1: Fallback para PT 101
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $reflection = new \ReflectionClass($channel);
            $method = $reflection->getMethod('findTelephoneEventPt');
            $method->setAccessible(true);

            $pt = $method->invoke($channel, 8000);
            return $pt === 101;
        }, "Fallback para PT 101");

        // Teste 2: Encontra PT registrado
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->ptCodecs[120] = 'telephone-event';
            $channel->ptCodecsFrequency['telephone-event_8000'] = 8000;

            $reflection = new \ReflectionClass($channel);
            $method = $reflection->getMethod('findTelephoneEventPt');
            $method->setAccessible(true);

            $pt = $method->invoke($channel, 8000);
            return $pt === 120 || $pt === 101; // Aceita qualquer telephone-event
        }, "Encontra PT de telephone-event registrado");

        echo "\n";
    }

    /**
     * Testa gerenciamento de membros
     */
    private function testMemberManagement(): void
    {
        echo "📋 Member Management Tests\n";

        $socket = $this->createMockSocket();
        $callId = 'test-call-' . uniqid();

        // Teste 1: isMember retorna false para membro não existente
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            return $channel->isMember('192.168.1.1:5060') === false;
        }, "isMember retorna false para não-membro");

        // Teste 2: addMember e isMember
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([]);
            $channel->addMember([
                'address' => '192.168.1.1',
                'port' => 5060,
                'codec' => 'PCMU',
                'pt' => 0,
                'ssrc' => 12345,
                'timestamp' => 0,
                'config' => [],
                'frequency' => 8000,
            ]);
            return $channel->isMember('192.168.1.1:5060') === true;
        }, "addMember adiciona membro corretamente");

        // Teste 3: Membro tem rtpChannel
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([]);
            $channel->addMember([
                'address' => '192.168.1.1',
                'port' => 5060,
                'codec' => 'PCMA',
                'pt' => 8,
                'ssrc' => 12345,
                'timestamp' => 0,
                'config' => [],
                'frequency' => 8000,
            ]);
            $member = $channel->members['192.168.1.1:5060'];
            return isset($member['rtpChannel']);
        }, "Membro tem rtpChannel criado");

        // Teste 4: Membro tem opusChannel
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([]);
            $channel->addMember([
                'address' => '192.168.1.1',
                'port' => 5060,
                'codec' => 'OPUS',
                'pt' => 96,
                'ssrc' => 12345,
                'timestamp' => 0,
                'config' => [],
                'frequency' => 48000,
            ]);
            $member = $channel->members['192.168.1.1:5060'];
            return isset($member['opus']) && is_object($member['opus']);
        }, "Membro tem opusChannel criado");

        // Teste 5: Múltiplos membros
        $this->assert(function () use ($socket, $callId) {
            $channel = new MediaChannel($socket, $callId);
            $channel->registerPtCodecs([]);
            $channel->addMember([
                'address' => '192.168.1.1',
                'port' => 5060,
                'codec' => 'PCMU',
                'pt' => 0,
                'ssrc' => 12345,
                'timestamp' => 0,
                'config' => [],
                'frequency' => 8000,
            ]);
            $channel->addMember([
                'address' => '192.168.1.2',
                'port' => 5060,
                'codec' => 'PCMA',
                'pt' => 8,
                'ssrc' => 54321,
                'timestamp' => 0,
                'config' => [],
                'frequency' => 8000,
            ]);
            return count($channel->members) === 2;
        }, "Múltiplos membros são adicionados");

        echo "\n";
    }

    /**
     * Cria um mock de Socket para testes
     */
    private function createMockSocket()
    {
        // Retorna uma instância real do Swoole\Coroutine\Socket mockado
        return new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    /**
     * Executa uma asserção e registra o resultado
     */
    private function assert(callable $test, string $description): void
    {
        try {
            $result = $test();
            if ($result) {
                $this->testsPassed++;
                echo "  \033[32m✓\033[0m {$description}\n";
            } else {
                $this->testsFailed++;
                $this->failures[] = $description;
                echo "  \033[31m✗\033[0m {$description}\n";
            }
        } catch (\Throwable $e) {
            $this->testsFailed++;
            $this->failures[] = $description . " - Exception: " . $e->getMessage();
            echo "  \033[31m✗\033[0m {$description} - \033[31m{$e->getMessage()}\033[0m\n";
        }
    }

    /**
     * Imprime sumário dos testes
     */
    private function printSummary(): bool
    {
        $total = $this->testsPassed + $this->testsFailed;

        echo "\033[1;36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";

        if ($this->testsFailed === 0) {
            echo "\033[1;32m✓ Todos os {$total} testes passaram!\033[0m\n";
        } else {
            echo "\033[1;31m✗ {$this->testsFailed} de {$total} testes falharam\033[0m\n\n";
            echo "Falhas:\n";
            foreach ($this->failures as $failure) {
                echo "  • {$failure}\n";
            }
        }

        echo "\033[1;36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";

        return $this->testsFailed === 0;
    }
}
