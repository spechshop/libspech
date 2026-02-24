<?php

namespace Tests;

/**
 * Validador de pacotes RTP conforme RFC 3550
 */
class RtpPacketValidator
{
    private array $errors = [];

    /**
     * Valida um pacote RTP completo
     */
    public function validate(string $packet): bool
    {
        $this->errors = [];

        if (strlen($packet) < 12) {
            $this->errors[] = "Pacote muito curto (mínimo 12 bytes para header)";
            return false;
        }

        $header = $this->parseHeader($packet);

        // Validar version (deve ser 2)
        if ($header['version'] !== 2) {
            $this->errors[] = "RTP Version inválida: {$header['version']} (esperado: 2)";
        }

        // Validar payload type (0-127)
        if ($header['payloadType'] < 0 || $header['payloadType'] > 127) {
            $this->errors[] = "Payload type inválido: {$header['payloadType']}";
        }

        // Validar tamanho do payload
        $payloadSize = strlen($packet) - 12;
        if ($payloadSize < 0) {
            $this->errors[] = "Payload size negativo: {$payloadSize}";
        }

        return empty($this->errors);
    }

    /**
     * Parse do header RTP (12 bytes)
     */
    public function parseHeader(string $packet): array
    {
        $data = unpack('C2bytes/nseq/Ntimestamp/Nssrc', $packet);

        $firstByte = $data['bytes1'];
        $secondByte = $data['bytes2'];

        return [
            'version' => ($firstByte >> 6) & 0x03,
            'padding' => ($firstByte >> 5) & 0x01,
            'extension' => ($firstByte >> 4) & 0x01,
            'csrcCount' => $firstByte & 0x0F,
            'marker' => ($secondByte >> 7) & 0x01,
            'payloadType' => $secondByte & 0x7F,
            'sequenceNumber' => $data['seq'],
            'timestamp' => $data['timestamp'],
            'ssrc' => $data['ssrc'],
        ];
    }

    /**
     * Extrai o payload do pacote RTP
     */
    public function getPayload(string $packet): string
    {
        return substr($packet, 12);
    }

    /**
     * Valida sequência de pacotes
     */
    public function validateSequence(array $packets): bool
    {
        $this->errors = [];

        if (count($packets) < 2) {
            return true; // Não há sequência para validar
        }

        $prevSeq = null;
        foreach ($packets as $i => $packet) {
            $header = $this->parseHeader($packet);
            $seq = $header['sequenceNumber'];

            if ($prevSeq !== null) {
                $expectedSeq = ($prevSeq + 1) & 0xFFFF;
                if ($seq !== $expectedSeq) {
                    $this->errors[] = "Sequência quebrada no pacote {$i}: esperado {$expectedSeq}, obtido {$seq}";
                }
            }

            $prevSeq = $seq;
        }

        return empty($this->errors);
    }

    /**
     * Valida que o SSRC é consistente em todos os pacotes
     */
    public function validateSsrc(array $packets): bool
    {
        $this->errors = [];

        if (empty($packets)) {
            return true;
        }

        $firstHeader = $this->parseHeader($packets[0]);
        $expectedSsrc = $firstHeader['ssrc'];

        foreach ($packets as $i => $packet) {
            $header = $this->parseHeader($packet);
            if ($header['ssrc'] !== $expectedSsrc) {
                $this->errors[] = "SSRC inconsistente no pacote {$i}: esperado {$expectedSsrc}, obtido {$header['ssrc']}";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida incremento de timestamp
     */
    public function validateTimestampIncrement(array $packets, int $expectedIncrement): bool
    {
        $this->errors = [];

        if (count($packets) < 2) {
            return true;
        }

        $prevTimestamp = null;
        foreach ($packets as $i => $packet) {
            $header = $this->parseHeader($packet);
            $timestamp = $header['timestamp'];

            if ($prevTimestamp !== null) {
                $actualIncrement = $timestamp - $prevTimestamp;
                if ($actualIncrement !== $expectedIncrement) {
                    $this->errors[] = "Incremento de timestamp incorreto no pacote {$i}: esperado {$expectedIncrement}, obtido {$actualIncrement}";
                }
            }

            $prevTimestamp = $timestamp;
        }

        return empty($this->errors);
    }

    /**
     * Retorna os erros encontrados
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Formata header para exibição
     */
    public function formatHeader(array $header): string
    {
        return sprintf(
            "V=%d P=%d X=%d CC=%d M=%d PT=%d Seq=%d TS=%u SSRC=%u",
            $header['version'],
            $header['padding'],
            $header['extension'],
            $header['csrcCount'],
            $header['marker'],
            $header['payloadType'],
            $header['sequenceNumber'],
            $header['timestamp'],
            $header['ssrc']
        );
    }
}
