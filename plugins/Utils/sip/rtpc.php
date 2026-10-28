<?php

namespace libspech\Rtp;

use libspech\Cli\cli;

class rtpc
{
    private string $format = 'CCnNN';

    public string $rawPacket = '';

    // Owned strings only: the receive buffer is never retained by this object.
    private ?string $originalHeader = null;
    private string $originalPayload = '';

    public int $version = 2;
    public int $padding = 0;
    public int $extension = 0;
    public int $cc = 0;
    public int $marker = 0;
    public int $payloadType = 0;
    public int $sequence = 0;
    public int $timestamp = 0;
    public int $ssrc = 0;

    public string $payloadRaw = '';

    public function __construct(?string $packet)
    {
        if ($packet === null || strlen($packet) < 12) {
            return;
        }

        $this->rawPacket = $packet;


        $this->parseFixedHeader($packet);

        /*
         * RTP possui cabeçalho mínimo de 12 bytes.
         *
         * Cada CSRC acrescenta 4 bytes.
         */
        $payloadOffset = 12 + ($this->cc * 4);

        /*
         * Se X=1, depois da lista CSRC existe:
         *
         * 16 bits: profile
         * 16 bits: length em words de 32 bits
         * N words: extension data
         */
        if ($this->extension === 1) {
            if (strlen($packet) < ($payloadOffset + 4)) {
                return;
            }

            $extensionLength = unpack(
                'nlength',
                $packet,
                $payloadOffset + 2
            );

            $payloadOffset += 4 + ($extensionLength['length'] * 4);
        }

        if ($payloadOffset > strlen($packet)) {
            return;
        }

        $this->payloadRaw = substr($packet, $payloadOffset);
    }

    /**
     * $packetLength is the producer's valid byte count (recvfromInto's result).
     * All reads finish synchronously, before the receive storage can be reused.
     */
    public static function fromNativeBuffer(object $source, int $packetLength): self
    {
        if ($packetLength < 0) {
            throw new \ValueError('Packet length must be greater than or equal to 0');
        }
        $packet = new self(null);
        // Preserve the string constructor's defaults, including rawPacket=''.
        if ($packetLength < 12) {
            return $packet;
        }

        $header = \psampler_binary_slice($source, 0, 12);
        $packet->parseFixedHeader($header);
        $payloadOffset = 12 + ($packet->cc * 4);
        if ($packet->extension === 1) {
            if ($packetLength < $payloadOffset + 4) {
                $packet->rawPacket = \psampler_binary_slice($source, 0, $packetLength);
                return $packet;
            }
            $extensionLength = unpack('nlength', \psampler_binary_slice($source, $payloadOffset + 2, 2));
            $payloadOffset += 4 + ($extensionLength['length'] * 4);
        }
        if ($payloadOffset > $packetLength) {
            // Malformed packets retain the original parser's partial fields and
            // complete rawPacket. This exceptional path materializes eagerly.
            $packet->rawPacket = \psampler_binary_slice($source, 0, $packetLength);
            return $packet;
        }

        $packet->originalHeader = $payloadOffset === 12
            ? $header
            : \psampler_binary_slice($source, 0, $payloadOffset);
        $packet->payloadRaw = \psampler_binary_slice($source, $payloadOffset, $packetLength - $payloadOffset);
        $packet->originalPayload = $packet->payloadRaw;
        // Keep a real public typed property: direct writes and references retain
        // Zend's usual type checks. Only its first read needs interception.
        unset($packet->rawPacket);
        return $packet;
    }

    public function &__get(string $name): mixed
    {
        if ($name === 'rawPacket' && $this->originalHeader !== null) {
            $this->rawPacket = $this->originalHeader . $this->originalPayload;
            $this->originalHeader = null;
            $this->originalPayload = '';
            return $this->rawPacket;
        }
        if (property_exists($this, $name)) {
            $property = new \ReflectionProperty($this, $name);
            if (!$property->isPublic()) {
                throw new \Error('Cannot access ' . ($property->isPrivate() ? 'private' : 'protected')
                    . ' property ' . $property->getDeclaringClass()->getName() . '::$' . $name);
            }
        }
        // Let Zend report undefined/uninitialized properties as usual.
        $value = $this->{$name};
        return $value;
    }

    public function __isset(string $name): bool
    {
        return $name === 'rawPacket' && $this->originalHeader !== null;
    }

    public function __serialize(): array
    {
        if ($this->originalHeader !== null) {
            // Serialize the public snapshot, including an explicit replacement.
            $rawPacket = $this->rawPacket;
            $this->originalHeader = null;
            $this->originalPayload = '';
        }
        $properties = (array)$this;
        unset($properties["\0" . self::class . "\0originalHeader"], $properties["\0" . self::class . "\0originalPayload"]);
        return $properties;
    }

    private function parseFixedHeader(string $packet): void
    {
        $header = unpack(
            'Cfirst/Csecond/nsequence/Ntimestamp/Nssrc',
            $packet
        );

        $firstByte = $header['first'];
        $secondByte = $header['second'];

        $this->version = $firstByte >> 6;
        $this->padding = ($firstByte >> 5) & 0x01;
        $this->extension = ($firstByte >> 4) & 0x01;
        $this->cc = $firstByte & 0x0F;

        $this->marker = ($secondByte >> 7) & 0x01;
        $this->payloadType = $secondByte & 0x7F;

        $this->sequence = $header['sequence'];
        $this->timestamp = $header['timestamp'];
        $this->ssrc = $header['ssrc'];
    }

    public function getCodec(): int
    {
        return $this->payloadType & 0x7F;
    }

    public function setPayloadType($payloadType = 0): void
    {
        if (!$payloadType) {
            $payloadType = 0;
        }

        $this->payloadType = $payloadType & 0x7F;
    }

    public function setSequence(int $sequence): void
    {
        $this->sequence = $sequence;
    }

    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function setSsrc(int $ssrc): void
    {
        $this->ssrc = $ssrc;
    }

    public function setMarker(int $marker): void
    {
        $this->marker = $marker & 0x01;
    }

    /**
     * Constrói o pacote RTP completo com o payload codificado
     * usando os valores atuais do cabeçalho.
     *
     * @param false|string $encoded
     * @return false|string
     */
    public function build(false|string $encoded): string
    {
        if ($encoded === false || $encoded === '') {
            return false;
        }

        $this->payloadRaw = $encoded;

        $firstByte =
            (($this->version & 0x03) << 6) |
            (($this->padding & 0x01) << 5) |
            (($this->extension & 0x01) << 4) |
            ($this->cc & 0x0F);

        $secondByte =
            (($this->marker & 0x01) << 7) |
            ($this->payloadType & 0x7F);

        $packet = pack(
                $this->format,
                $firstByte,
                $secondByte,
                $this->sequence,
                $this->timestamp,
                $this->ssrc
            ) . $this->payloadRaw;

        $this->rawPacket = $packet;

        return $packet;
    }

    public function verbose(): void
    {
        $message =
            "$this->ssrc: seq:$this->sequence " .
            "ts:$this->timestamp " .
            "pt:$this->payloadType real ts:" .
            str_replace('.', '', (string) microtime(true));

        cli::pcl($message, 'green');
    }

    public function getSequence()
    {
        return $this->sequence;
    }
}
