<?php

namespace libspech\Rtp;

use libspech\Cli\cli;

class rtpc
{
    private string $format = 'CCnNN';

    public string $rawPacket = '';

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