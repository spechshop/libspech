<?php

namespace Tests;

/**
 * Validador de eventos DTMF conforme RFC 2833
 */
class DtmfValidator
{
    private array $errors = [];

    /**
     * Valida payload DTMF (4 bytes)
     */
    public function validatePayload(string $payload): bool
    {
        $this->errors = [];

        if (strlen($payload) !== 4) {
            $this->errors[] = "Payload DTMF deve ter 4 bytes, obtido: " . strlen($payload);
            return false;
        }

        $data = $this->parsePayload($payload);

        // Validar event code (0-15)
        if ($data['event'] < 0 || $data['event'] > 15) {
            $this->errors[] = "Event code inválido: {$data['event']} (deve estar entre 0-15)";
        }

        // Validar volume (0-63)
        if ($data['volume'] < 0 || $data['volume'] > 63) {
            $this->errors[] = "Volume inválido: {$data['volume']} (deve estar entre 0-63)";
        }

        // Validar duration (deve ser positivo)
        if ($data['duration'] < 0) {
            $this->errors[] = "Duration negativa: {$data['duration']}";
        }

        return empty($this->errors);
    }

    /**
     * Parse do payload DTMF (4 bytes)
     */
    public function parsePayload(string $payload): array
    {
        $data = unpack('Cevent/Cflags/nduration', $payload);

        return [
            'event' => $data['event'],
            'end' => ($data['flags'] >> 7) & 0x01,
            'reserved' => ($data['flags'] >> 6) & 0x01,
            'volume' => $data['flags'] & 0x3F,
            'duration' => $data['duration'],
        ];
    }

    /**
     * Valida que o marker bit está correto no primeiro pacote
     */
    public function validateMarkerBit(RtpPacketValidator $rtpValidator, string $firstPacket): bool
    {
        $this->errors = [];

        $header = $rtpValidator->parseHeader($firstPacket);

        if ($header['marker'] !== 1) {
            $this->errors[] = "Marker bit deve estar definido no primeiro pacote DTMF";
            return false;
        }

        return true;
    }

    /**
     * Valida sequência de eventos DTMF
     */
    public function validateDtmfSequence(RtpPacketValidator $rtpValidator, array $packets): bool
    {
        $this->errors = [];

        if (empty($packets)) {
            $this->errors[] = "Sequência DTMF vazia";
            return false;
        }

        // Primeiro pacote deve ter marker bit
        $firstHeader = $rtpValidator->parseHeader($packets[0]);
        if ($firstHeader['marker'] !== 1) {
            $this->errors[] = "Primeiro pacote deve ter marker bit = 1";
        }

        // Demais pacotes não devem ter marker bit
        for ($i = 1; $i < count($packets); $i++) {
            $header = $rtpValidator->parseHeader($packets[$i]);
            if ($header['marker'] === 1) {
                $this->errors[] = "Pacote {$i} não deve ter marker bit = 1";
            }
        }

        // Validar que o timestamp é fixo durante o evento
        $firstTimestamp = $firstHeader['timestamp'];
        foreach ($packets as $i => $packet) {
            $header = $rtpValidator->parseHeader($packet);
            if ($header['timestamp'] !== $firstTimestamp) {
                $this->errors[] = "Timestamp deve ser fixo durante evento DTMF. Pacote {$i}: esperado {$firstTimestamp}, obtido {$header['timestamp']}";
            }
        }

        // Validar que a duração é crescente
        $prevDuration = null;
        foreach ($packets as $i => $packet) {
            $payload = $rtpValidator->getPayload($packet);
            $dtmfData = $this->parsePayload($payload);

            if ($prevDuration !== null && $dtmfData['duration'] < $prevDuration) {
                $this->errors[] = "Duração deve ser crescente. Pacote {$i}: esperado >= {$prevDuration}, obtido {$dtmfData['duration']}";
            }

            $prevDuration = $dtmfData['duration'];
        }

        return empty($this->errors);
    }

    /**
     * Valida os 3 pacotes finais de um evento DTMF
     */
    public function validateFinalPackets(RtpPacketValidator $rtpValidator, array $finalPackets): bool
    {
        $this->errors = [];

        if (count($finalPackets) !== 3) {
            $this->errors[] = "Devem haver exatamente 3 pacotes finais, obtido: " . count($finalPackets);
            return false;
        }

        // Todos devem ter end bit = 1
        foreach ($finalPackets as $i => $packet) {
            $payload = $rtpValidator->getPayload($packet);
            $dtmfData = $this->parsePayload($payload);

            if ($dtmfData['end'] !== 1) {
                $this->errors[] = "Pacote final {$i} deve ter end bit = 1";
            }
        }

        // Todos devem ter a mesma duração
        $firstPayload = $rtpValidator->getPayload($finalPackets[0]);
        $firstDtmfData = $this->parsePayload($firstPayload);
        $expectedDuration = $firstDtmfData['duration'];

        foreach ($finalPackets as $i => $packet) {
            $payload = $rtpValidator->getPayload($packet);
            $dtmfData = $this->parsePayload($payload);

            if ($dtmfData['duration'] !== $expectedDuration) {
                $this->errors[] = "Pacotes finais devem ter a mesma duração. Pacote {$i}: esperado {$expectedDuration}, obtido {$dtmfData['duration']}";
            }
        }

        // Todos devem ter o mesmo timestamp
        $firstHeader = $rtpValidator->parseHeader($finalPackets[0]);
        $expectedTimestamp = $firstHeader['timestamp'];

        foreach ($finalPackets as $i => $packet) {
            $header = $rtpValidator->parseHeader($packet);
            if ($header['timestamp'] !== $expectedTimestamp) {
                $this->errors[] = "Pacotes finais devem ter o mesmo timestamp. Pacote {$i}: esperado {$expectedTimestamp}, obtido {$header['timestamp']}";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida evento DTMF completo (início + continuação + finais)
     */
    public function validateCompleteEvent(RtpPacketValidator $rtpValidator, array $allPackets): bool
    {
        $this->errors = [];

        if (count($allPackets) < 4) {
            $this->errors[] = "Evento DTMF completo deve ter no mínimo 4 pacotes (1 início + 3 finais)";
            return false;
        }

        // Separar pacotes normais dos 3 finais
        $finalPackets = array_slice($allPackets, -3);
        $normalPackets = array_slice($allPackets, 0, -3);

        // Validar sequência normal
        if (!$this->validateDtmfSequence($rtpValidator, $normalPackets)) {
            return false;
        }

        // Validar pacotes finais
        if (!$this->validateFinalPackets($rtpValidator, $finalPackets)) {
            return false;
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
     * Formata payload DTMF para exibição
     */
    public function formatPayload(array $dtmfData): string
    {
        return sprintf(
            "Event=%d End=%d Volume=%d Duration=%d",
            $dtmfData['event'],
            $dtmfData['end'],
            $dtmfData['volume'],
            $dtmfData['duration']
        );
    }
}
