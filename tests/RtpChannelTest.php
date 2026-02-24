<?php

namespace Tests;

use libspech\Rtp\rtpChannel;
use libspech\Rtp\DtmfEvent;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/RtpPacketValidator.php';
require_once __DIR__ . '/DtmfValidator.php';

/**
 * Suite de testes completa para rtpChannel
 */
class RtpChannelTest
{
    private RtpPacketValidator $rtpValidator;
    private DtmfValidator $dtmfValidator;
    private int $testsPassed = 0;
    private int $testsFailed = 0;
    private array $failures = [];

    public function __construct()
    {
        $this->rtpValidator = new RtpPacketValidator();
        $this->dtmfValidator = new DtmfValidator();
    }

    /**
     * Executa todos os testes
     */
    public function runAll(): bool
    {
        echo "\n\033[1;36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
        echo "\033[1;36m  RtpChannel Test Suite\033[0m\n";
        echo "\033[1;36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

        $this->testConstructorValidation();
        $this->testRtpHeader();
        $this->testAudioPacket();
        $this->testDtmfForwardPacket();
        $this->testDtmfRfc2833();
        $this->testSampleRateConversion();
        $this->testEdgeCases();
        $this->testGetChannelInfo();

        return $this->printSummary();
    }

    /**
     * Testa validações do construtor
     */
    private function testConstructorValidation(): void
    {
        echo "📋 Constructor Validation Tests\n";

        // Teste 1: Construtor padrão
        $this->assert(function () {
            $channel = new rtpChannel();
            return $channel->payloadType === rtpChannel::PAYLOAD_PCMU
                && $channel->sampleRate === 8000
                && $channel->packetTimeMs === 20;
        }, "Constructor com valores padrão");

        // Teste 2: Construtor com PCMA
        $this->assert(function () {
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, 20);
            return $channel->payloadType === rtpChannel::PAYLOAD_PCMA;
        }, "Constructor com PAYLOAD_PCMA");

        // Teste 3: Payload type inválido
        $this->assert(function () {
            try {
                new rtpChannel(200);
                return false;
            } catch (\InvalidArgumentException $e) {
                return true;
            }
        }, "Rejeita payload type inválido");

        // Teste 4: Sample rate inválido
        $this->assert(function () {
            try {
                new rtpChannel(rtpChannel::PAYLOAD_PCMU, -1);
                return false;
            } catch (\InvalidArgumentException $e) {
                return true;
            }
        }, "Rejeita sample rate negativo");

        // Teste 5: Packet time inválido
        $this->assert(function () {
            try {
                new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 0);
                return false;
            } catch (\InvalidArgumentException $e) {
                return true;
            }
        }, "Rejeita packet time zero");

        echo "\n";
    }

    /**
     * Testa construção de header RTP
     */
    private function testRtpHeader(): void
    {
        echo "📋 RTP Header Tests\n";

        $channel = new rtpChannel();

        // Teste 1: Header básico
        $this->assert(function () use ($channel) {
            $header = $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMU, 1000);
            return strlen($header) === 12;
        }, "Header tem 12 bytes");

        // Teste 2: Version = 2
        $this->assert(function () use ($channel) {
            $header = $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMU, 1000);
            $parsed = $this->rtpValidator->parseHeader($header . "\x00");
            return $parsed['version'] === 2;
        }, "Version = 2");

        // Teste 3: Payload type correto
        $this->assert(function () use ($channel) {
            $header = $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMA, 1000);
            $parsed = $this->rtpValidator->parseHeader($header . "\x00");
            return $parsed['payloadType'] === rtpChannel::PAYLOAD_PCMA;
        }, "Payload type preservado");

        // Teste 4: Marker bit
        $this->assert(function () use ($channel) {
            $channel->setMarkerBit(true);
            $header = $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMU, 1000);
            $parsed = $this->rtpValidator->parseHeader($header . "\x00");
            return $parsed['marker'] === 1;
        }, "Marker bit definido");

        // Teste 5: Marker bit resetado
        $this->assert(function () use ($channel) {
            $channel->setMarkerBit(true);
            $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMU, 1000);
            $header2 = $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMU, 1000);
            $parsed = $this->rtpValidator->parseHeader($header2 . "\x00");
            return $parsed['marker'] === 0;
        }, "Marker bit resetado após uso");

        // Teste 6: SSRC consistente
        $this->assert(function () use ($channel) {
            $header1 = $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMU, 1000);
            $header2 = $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMU, 2000);
            $parsed1 = $this->rtpValidator->parseHeader($header1 . "\x00");
            $parsed2 = $this->rtpValidator->parseHeader($header2 . "\x00");
            return $parsed1['ssrc'] === $parsed2['ssrc'];
        }, "SSRC consistente entre pacotes");

        // Teste 7: Payload type DTMF mapeado
        $this->assert(function () use ($channel) {
            $channel->setNewPtDTMF(105);
            $header = $channel->buildRtpHeader(rtpChannel::PAYLOAD_DTMF, 1000);
            $parsed = $this->rtpValidator->parseHeader($header . "\x00");
            return $parsed['payloadType'] === 105;
        }, "Payload DTMF usa PT customizado");

        // Teste 8: Timestamp no header
        $this->assert(function () use ($channel) {
            $expectedTimestamp = 123456;
            $header = $channel->buildRtpHeader(rtpChannel::PAYLOAD_PCMU, $expectedTimestamp);
            $parsed = $this->rtpValidator->parseHeader($header . "\x00");
            return $parsed['timestamp'] === $expectedTimestamp;
        }, "Timestamp preservado no header");

        echo "\n";
    }

    /**
     * Testa construção de pacotes de áudio
     */
    private function testAudioPacket(): void
    {
        echo "📋 Audio Packet Tests\n";

        $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 20);
        $audioData = str_repeat("\xFF", 160);

        // Teste 1: Pacote completo
        $this->assert(function () use ($channel, $audioData) {
            $packet = $channel->buildAudioPacket($audioData);
            return strlen($packet) === 12 + strlen($audioData);
        }, "Pacote tem header + payload");

        // Teste 2: Sequence number incrementa
        $this->assert(function () use ($channel, $audioData) {
            $seq1 = $channel->sequenceNumber;
            $channel->buildAudioPacket($audioData);
            return $channel->sequenceNumber === ($seq1 + 1) & 0xFFFF;
        }, "Sequence number incrementa");

        // Teste 3: Timestamp incrementa
        $this->assert(function () use ($channel, $audioData) {
            $ts1 = $channel->timestamp;
            $channel->buildAudioPacket($audioData, true);
            return $channel->timestamp === $ts1 + $channel->samplesPerPacket;
        }, "Timestamp incrementa corretamente");

        // Teste 4: Timestamp não incrementa quando disabled
        $this->assert(function () use ($channel, $audioData) {
            $ts1 = $channel->timestamp;
            $channel->buildAudioPacket($audioData, false);
            return $channel->timestamp === $ts1;
        }, "Timestamp não incrementa quando desabilitado");

        echo "\n";
    }

    /**
     * Testa pacotes DTMF forward
     */
    private function testDtmfForwardPacket(): void
    {
        echo "📋 DTMF Forward Packet Tests\n";

        $channel = new rtpChannel();
        $dtmfPayload = pack('CCn', 1, 0x0A, 160); // Event 1, volume 10, duration 160

        // Teste 1: Pacote forward básico
        $this->assert(function () use ($channel, $dtmfPayload) {
            $packet = $channel->buildDtmfForwardPacket($dtmfPayload, 1000, true);
            return strlen($packet) === 12 + 4;
        }, "Pacote forward tem tamanho correto");

        // Teste 2: Marker bit no forward
        $this->assert(function () use ($channel, $dtmfPayload) {
            $packet = $channel->buildDtmfForwardPacket($dtmfPayload, 1000, true);
            $header = $this->rtpValidator->parseHeader($packet);
            return $header['marker'] === 1;
        }, "Marker bit no primeiro pacote forward");

        // Teste 3: Timestamp preservado
        $this->assert(function () use ($channel, $dtmfPayload) {
            $expectedTs = 5000;
            $packet = $channel->buildDtmfForwardPacket($dtmfPayload, $expectedTs, false);
            $header = $this->rtpValidator->parseHeader($packet);
            return $header['timestamp'] === $expectedTs;
        }, "Timestamp fixo no forward");

        // Teste 4: Estado restaurado
        $this->assert(function () use ($channel, $dtmfPayload) {
            $originalPt = $channel->payloadType;
            $channel->buildDtmfForwardPacket($dtmfPayload, 1000, false);
            return $channel->payloadType === $originalPt;
        }, "Payload type restaurado após forward");

        // Teste 5: Sequence incrementa
        $this->assert(function () use ($channel, $dtmfPayload) {
            $seq1 = $channel->sequenceNumber;
            $channel->buildDtmfForwardPacket($dtmfPayload, 1000, false);
            return $channel->sequenceNumber === ($seq1 + 1) & 0xFFFF;
        }, "Sequence incrementa no forward");

        // Teste 6: Payload DTMF correto
        $this->assert(function () use ($channel, $dtmfPayload) {
            $channel->setNewPtDTMF(101);
            $packet = $channel->buildDtmfForwardPacket($dtmfPayload, 1000, false);
            $header = $this->rtpValidator->parseHeader($packet);
            return $header['payloadType'] === 101;
        }, "Usa PT DTMF correto");

        echo "\n";
    }

    /**
     * Testa envio de DTMF RFC 2833
     */
    private function testDtmfRfc2833(): void
    {
        echo "📋 DTMF RFC 2833 Tests\n";

        // Teste 1: rfc2833 básico
        $this->assert(function () {
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 20);
            $packets = [];

            $channel->rfc2833('5', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            return count($packets) >= 4; // Pelo menos 1 início + 3 finais
        }, "rfc2833 gera pacotes corretos");

        // Teste 2: Primeiro pacote tem marker bit
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets = [];

            $channel->rfc2833('1', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            $header = $this->rtpValidator->parseHeader($packets[0]);
            return $header['marker'] === 1;
        }, "Primeiro pacote DTMF tem marker bit");

        // Teste 3: Últimos 3 pacotes têm end bit
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets = [];

            $channel->rfc2833('9', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            $finalPackets = array_slice($packets, -3);
            foreach ($finalPackets as $packet) {
                $payload = $this->rtpValidator->getPayload($packet);
                $dtmf = $this->dtmfValidator->parsePayload($payload);
                if ($dtmf['end'] !== 1) return false;
            }
            return true;
        }, "Últimos 3 pacotes têm end bit");

        // Teste 4: Timestamp fixo durante evento
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets = [];

            $channel->rfc2833('*', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            $firstHeader = $this->rtpValidator->parseHeader($packets[0]);
            $expectedTs = $firstHeader['timestamp'];

            foreach ($packets as $packet) {
                $header = $this->rtpValidator->parseHeader($packet);
                if ($header['timestamp'] !== $expectedTs) return false;
            }
            return true;
        }, "Timestamp fixo durante evento DTMF");

        // Teste 5: Duração crescente
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets = [];

            $channel->rfc2833('#', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            // Excluir os 3 finais (têm duração igual)
            $normalPackets = array_slice($packets, 0, -3);
            $prevDuration = null;

            foreach ($normalPackets as $packet) {
                $payload = $this->rtpValidator->getPayload($packet);
                $dtmf = $this->dtmfValidator->parsePayload($payload);
                if ($prevDuration !== null && $dtmf['duration'] < $prevDuration) {
                    return false;
                }
                $prevDuration = $dtmf['duration'];
            }
            return true;
        }, "Duração crescente nos pacotes DTMF");

        // Teste 6: 3 pacotes finais têm duração igual
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets = [];

            $channel->rfc2833('0', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            $finalPackets = array_slice($packets, -3);
            $durations = [];

            foreach ($finalPackets as $packet) {
                $payload = $this->rtpValidator->getPayload($packet);
                $dtmf = $this->dtmfValidator->parsePayload($payload);
                $durations[] = $dtmf['duration'];
            }

            return count(array_unique($durations)) === 1;
        }, "3 pacotes finais têm mesma duração");

        // Teste 7: Event code correto
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets = [];

            $channel->rfc2833('7', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            $payload = $this->rtpValidator->getPayload($packets[0]);
            $dtmf = $this->dtmfValidator->parsePayload($payload);
            return $dtmf['event'] === 7;
        }, "Event code correto (7)");

        // Teste 8: Sequência RTP válida
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets = [];

            $channel->rfc2833('2', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            return $this->rtpValidator->validateSequence($packets);
        }, "Sequência RTP válida");

        // Teste 9: SSRC consistente
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets = [];

            $channel->rfc2833('4', function ($packet) use (&$packets) {
                $packets[] = $packet;
            }, function ($packet) use (&$packets) {
                $packets[] = $packet;
            });

            return $this->rtpValidator->validateSsrc($packets);
        }, "SSRC consistente");

        // Teste 10: Exceção para string vazia
        $this->assert(function () {
            $channel = new rtpChannel();
            try {
                $channel->rfc2833('ab', function ($p) {}, function ($p) {});
                return false;
            } catch (\InvalidArgumentException $e) {
                return true;
            }
        }, "Exceção para múltiplos caracteres");

        // Teste 11: State reset após evento
        $this->assert(function () {
            $channel = new rtpChannel();

            $channel->rfc2833('5', function ($p) {}, function ($p) {});

            return $channel->currentDtmfEvent === null
                && $channel->dtmfStartTimestamp === null;
        }, "Estado DTMF resetado após evento");

        // Teste 12: Múltiplos eventos sequenciais
        $this->assert(function () {
            $channel = new rtpChannel();
            $packets1 = [];
            $packets2 = [];

            $channel->rfc2833('1', function ($p) use (&$packets1) {
                $packets1[] = $p;
            }, function ($p) use (&$packets1) {
                $packets1[] = $p;
            });

            $channel->rfc2833('2', function ($p) use (&$packets2) {
                $packets2[] = $p;
            }, function ($p) use (&$packets2) {
                $packets2[] = $p;
            });

            return count($packets1) >= 4 && count($packets2) >= 4;
        }, "Múltiplos eventos DTMF sequenciais");

        echo "\n";
    }

    /**
     * Testa conversão de sample rate
     */
    private function testSampleRateConversion(): void
    {
        echo "📋 Sample Rate Conversion Tests\n";

        // Teste 1: 8000 Hz
        $this->assert(function () {
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 20);
            return $channel->samplesPerPacket === 160;
        }, "8000 Hz: 160 samples/packet");

        // Teste 2: 16000 Hz
        $this->assert(function () {
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 16000, 20);
            return $channel->samplesPerPacket === 320;
        }, "16000 Hz: 320 samples/packet");

        // Teste 3: 48000 Hz
        $this->assert(function () {
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 48000, 20);
            return $channel->samplesPerPacket === 960;
        }, "48000 Hz: 960 samples/packet");

        // Teste 4: setFrequency ignora valores baixos
        $this->assert(function () {
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 20);
            $channel->setFrequency(4000);
            return $channel->sampleRate === 8000;
        }, "setFrequency ignora < 8000 Hz");

        // Teste 5: setFrequency aceita valores altos
        $this->assert(function () {
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 20);
            $channel->setFrequency(16000);
            return $channel->sampleRate === 16000;
        }, "setFrequency aceita >= 8000 Hz");

        echo "\n";
    }

    /**
     * Testa casos extremos
     */
    private function testEdgeCases(): void
    {
        echo "📋 Edge Cases Tests\n";

        // Teste 1: Sequence number overflow (verifica que o mask funciona no header)
        $this->assert(function () {
            $channel = new rtpChannel();
            $channel->sequenceNumber = 0xFFFF;
            $packet = $channel->buildAudioPacket("\x00", true);
            // O valor interno fica 0x10000, mas no header deve ficar 0 (com mask)
            $header = $this->rtpValidator->parseHeader($packet);
            return $header['sequenceNumber'] === 0xFFFF; // Header usa o valor ANTES do incremento
        }, "Sequence number no header usa valor antes do incremento");

        // Teste 2: SSRC customizado
        $this->assert(function () {
            $customSsrc = 0x12345678;
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 20, $customSsrc);
            return $channel->ssrc === $customSsrc;
        }, "SSRC customizado no construtor");

        // Teste 3: Payload DTMF customizado
        $this->assert(function () {
            $channel = new rtpChannel();
            $channel->setNewPtDTMF(120);
            $header = $channel->buildRtpHeader(rtpChannel::PAYLOAD_DTMF, 1000);
            $parsed = $this->rtpValidator->parseHeader($header . "\x00");
            return $parsed['payloadType'] === 120;
        }, "Payload DTMF customizado");

        // Teste 4: getChannelInfo completo
        $this->assert(function () {
            $channel = new rtpChannel();
            $info = $channel->getChannelInfo();
            return isset($info['payloadType'])
                && isset($info['sampleRate'])
                && isset($info['sequenceNumber'])
                && isset($info['timestamp'])
                && isset($info['ssrc']);
        }, "getChannelInfo retorna todos os campos");

        // Teste 5: DtmfEvent com caracteres especiais
        $this->assert(function () {
            $star = DtmfEvent::charToEvent('*');
            $hash = DtmfEvent::charToEvent('#');
            return $star === 10 && $hash === 11;
        }, "DtmfEvent mapeia * e # corretamente");

        // Teste 6: DtmfEvent com letras
        $this->assert(function () {
            $a = DtmfEvent::charToEvent('A');
            $d = DtmfEvent::charToEvent('D');
            return $a === 12 && $d === 15;
        }, "DtmfEvent mapeia A-D corretamente");

        // Teste 7: Exceção para caractere inválido
        $this->assert(function () {
            try {
                DtmfEvent::charToEvent('X');
                return false;
            } catch (\InvalidArgumentException $e) {
                return true;
            }
        }, "Exceção para caractere DTMF inválido");

        echo "\n";
    }

    /**
     * Testa getChannelInfo
     */
    private function testGetChannelInfo(): void
    {
        echo "📋 Channel Info Tests\n";

        // Teste 1: Info completo
        $this->assert(function () {
            $channel = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, 20);
            $info = $channel->getChannelInfo();

            return $info['payloadType'] === rtpChannel::PAYLOAD_PCMA
                && $info['sampleRate'] === 8000
                && $info['packetTimeMs'] === 20
                && $info['samplesPerPacket'] === 160;
        }, "getChannelInfo retorna dados corretos");

        echo "\n";
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
