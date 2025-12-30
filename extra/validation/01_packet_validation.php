<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Rtp\DtmfEvent;
use libspech\Rtp\rtpChannel;

/**
 * Decodifica um cabeçalho RTP.
 */
function decodeRtpHeader(string $packet): array
{
    if (strlen($packet) < 12) {
        throw new Exception("Pacote muito curto para ser RTP (" . strlen($packet) . " bytes)");
    }
    $header = unpack('Cfirst/Csecond/nseq/Nts/Nssrc', substr($packet, 0, 12));
    return [
        'version' => ($header['first'] >> 6) & 0x03,
        'padding' => ($header['first'] >> 5) & 0x01,
        'extension' => ($header['first'] >> 4) & 0x01,
        'csrcCount' => $header['first'] & 0x0F,
        'marker' => ($header['second'] >> 7) & 0x01,
        'payloadType' => $header['second'] & 0x7F,
        'sequenceNumber' => $header['seq'],
        'timestamp' => $header['ts'],
        'ssrc' => $header['ssrc'],
        'payload' => substr($packet, 12)
    ];
}

function assertEqual($expected, $actual, $message)
{
    if ($expected !== $actual) {
        echo " [FAIL] $message (Esperado: $expected, Obtido: $actual)\n";
        return false;
    }
    echo " [OK] $message\n";
    return true;
}

echo "--- Iniciando Validação de Pacotes RTP ---\n\n";

// --- TESTE 1: Áudio PCM ---
echo "Teste 1: Validação de Pacotes de Áudio (PCM)\n";
$rtp = new rtpChannel(rtpChannel::PAYLOAD_PCMA, 8000, 20, 0x12345678);
$dummyPcm = str_repeat("\x55", 160); // 20ms de PCMA a 8000Hz = 160 bytes
$initialSeq = $rtp->sequenceNumber;
$initialTs = $rtp->timestamp;

$packet = $rtp->buildAudioPacket($dummyPcm, true);
$decoded = decodeRtpHeader($packet);

$success = true;
$success &= assertEqual(2, $decoded['version'], "Versão RTP deve ser 2");
$success &= assertEqual(rtpChannel::PAYLOAD_PCMA, $decoded['payloadType'], "Payload type deve ser PCMA (8)");
$success &= assertEqual($initialSeq & 0xffff, $decoded['sequenceNumber'], "Sequence number deve ser o inicial");
$success &= assertEqual($initialTs, $decoded['timestamp'], "Timestamp deve ser o inicial");
$success &= assertEqual(0x12345678, $decoded['ssrc'], "SSRC deve ser 0x12345678");
$success &= assertEqual($dummyPcm, $decoded['payload'], "Payload de áudio deve ser preservado");

// Próximo pacote
$packet2 = $rtp->buildAudioPacket($dummyPcm, true);
$decoded2 = decodeRtpHeader($packet2);
$success &= assertEqual(($initialSeq + 1) & 0xffff, $decoded2['sequenceNumber'], "Sequence number incrementado");
$success &= assertEqual($initialTs + 160, $decoded2['timestamp'], "Timestamp incrementado pelas amostras");

if ($success) echo "\nRESULTADO: Validação de Áudio PCM passou.\n\n";
else echo "\nRESULTADO: Validação de Áudio PCM falhou.\n\n";


// --- TESTE 2: G.729 ---
echo "Teste 2: Validação de Pacotes G.729\n";
$rtpG729 = new rtpChannel(rtpChannel::PAYLOAD_G729, 8000, 20, 0xABCDEF00);
$dummyG729 = str_repeat("\x00", 20); // G.729 a 8kbps, 20ms = 20 bytes (160 bits)
$initialSeqG = $rtpG729->sequenceNumber;
$initialTsG = $rtpG729->timestamp;

$packetG = $rtpG729->buildAudioPacket($dummyG729, true);
$decodedG = decodeRtpHeader($packetG);

$successG = true;
$successG &= assertEqual(rtpChannel::PAYLOAD_G729, $decodedG['payloadType'], "Payload type deve ser G729 (18)");
$successG &= assertEqual($initialTsG, $decodedG['timestamp'], "Timestamp deve ser o inicial");
$successG &= assertEqual($dummyG729, $decodedG['payload'], "Payload G.729 deve ser preservado");

if ($successG) echo "\nRESULTADO: Validação de G.729 passou.\n\n";
else echo "\nRESULTADO: Validação de G.729 falhou.\n\n";


// --- TESTE 3: DTMF (RFC 2833) ---
echo "Teste 3: Validação de Pacotes DTMF (RFC 2833)\n";
$rtpDtmf = new rtpChannel(rtpChannel::PAYLOAD_PCMU, 8000, 20, 0x99887766);
$rtpDtmf->setNewPtDTMF(101);
$dtmfString = "1";
$eventDurationMs = 100;
$packets = $rtpDtmf->generateDtmfSequence($dtmfString, $eventDurationMs);

echo "Gerados " . count($packets) . " pacotes para DTMF '$dtmfString' ($eventDurationMs ms).\n";

$successDtmf = true;
$firstTs = null;
$lastSeq = null;

foreach ($packets as $i => $p) {
    $d = decodeRtpHeader($p);

    // Validação de cabeçalho RTP
    if ($i === 0) {
        $successDtmf &= assertEqual(1, $d['marker'], "P0: Marker Bit deve ser 1");
        $firstTs = $d['timestamp'];
        $lastSeq = $d['sequenceNumber'];
    } else {
        // Para DTMF, marker bit é apenas no primeiro pacote do evento
        $successDtmf &= assertEqual(0, $d['marker'], "P$i: Marker Bit deve ser 0");
        $successDtmf &= assertEqual($firstTs, $d['timestamp'], "P$i: Timestamp deve ser constante ({$firstTs})");
        $successDtmf &= assertEqual(($lastSeq + 1) & 0xffff, $d['sequenceNumber'], "P$i: Sequence number deve ser contíguo");
        $lastSeq = $d['sequenceNumber'];
    }

    $successDtmf &= assertEqual(101, $d['payloadType'], "P$i: Payload type deve ser 101");

    // Validação de payload DTMF (RFC 2833)
    $dtmfPayload = unpack('Cevent/Cflags/nduration', $d['payload']);
    $successDtmf &= assertEqual(DtmfEvent::DTMF_1, $dtmfPayload['event'], "P$i: Evento DTMF deve ser 1");

    $isEnd = ($dtmfPayload['flags'] & 0x80) >> 7;
    $isFinalPackets = ($i >= count($packets) - 3); // Ultimos 3 pacotes de redundância

    if ($isFinalPackets) {
        $successDtmf &= assertEqual(1, $isEnd, "P$i: Bit 'End' deve ser 1 (pacote final)");
    } else {
        $successDtmf &= assertEqual(0, $isEnd, "P$i: Bit 'End' deve ser 0 (pacote inicial/intermediário)");
    }
}

if ($successDtmf) echo "\nRESULTADO: Validação de DTMF passou.\n\n";
else echo "\nRESULTADO: Validação de DTMF falhou.\n\n";

echo "--- Validação Concluída ---\n";
