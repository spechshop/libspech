<?php

declare(strict_types=1);

require __DIR__ . '/plugins/Utils/sip/rtpc.php';

use libspech\Rtp\rtpc;

if (!function_exists('psampler_binary_slice')) {
    fwrite(STDERR, "SKIP: psampler_binary_slice is required\n");
    exit(0);
}

function nativePacketCheck(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function nativePacket(string $bytes): rtpc
{
    $source = new ByteBuffer(max(1, strlen($bytes)));
    $source->append($bytes);
    $packet = rtpc::fromNativeBuffer($source, strlen($bytes));
    $source->clear();
    $source->append('storage has been reused');
    return $packet;
}

$fields = ['version', 'padding', 'extension', 'cc', 'marker', 'payloadType', 'sequence', 'timestamp', 'ssrc', 'payloadRaw'];
$payload = "\0encoded\xffaudio\0";
$base = pack('CCnNN', 0x80, 0x88, 65535, 0xfffffff0, 0x87654321);
$packets = ['', '0', str_repeat('x', 11), $base, $base . $payload];
// All first-byte combinations, including non-v2 packets and malformed bounds.
for ($first = 0; $first <= 255; ++$first) {
    $csrcs = str_repeat(pack('N', 0x12345678), $first & 15);
    $extension = ($first & 16) !== 0 ? pack('nn', 0xbede, 2) . "ext\0data" : '';
    $bytes = pack('CCnNN', $first, 0xef, 0, 0xffffffff, 0xffffffff) . $csrcs . $extension . $payload;
    $packets[] = $bytes;
    $packets[] = substr($bytes, 0, 12 + strlen($csrcs) - 1);
    if ($extension !== '') {
        $packets[] = substr($bytes, 0, 12 + strlen($csrcs) + 3);
        $packets[] = substr($bytes, 0, 12 + strlen($csrcs) + 5);
    }
}

foreach ($packets as $index => $bytes) {
    $traditional = new rtpc($bytes);
    $native = nativePacket($bytes);
    foreach ($fields as $field) {
        nativePacketCheck($native->{$field} === $traditional->{$field}, "packet $index: $field");
    }
    nativePacketCheck($native->getCodec() === $traditional->getCodec(), "packet $index: codec");
    nativePacketCheck(isset($native->rawPacket), "packet $index: isset");
    nativePacketCheck($native->rawPacket === $traditional->rawPacket, "packet $index: raw snapshot");
    nativePacketCheck(serialize($native) === serialize($traditional), "packet $index: serialization layout");
}

$native = nativePacket($base . $payload);
nativePacketCheck(!(new ReflectionProperty(rtpc::class, 'rawPacket'))->isInitialized($native), 'rawPacket remains lazy');
nativePacketCheck(isset($native->rawPacket), 'isset must not require materialization');
nativePacketCheck(!(new ReflectionProperty(rtpc::class, 'rawPacket'))->isInitialized($native), 'isset stays lazy');
$native->payloadRaw[0] = 'X';
$payloadReference = &$native->payloadRaw;
$payloadReference .= 'changed';
$native->sequence = 123;
$clone = clone $native;
nativePacketCheck($clone->rawPacket === $base . $payload, 'clone owns original bytes');
nativePacketCheck(!(new ReflectionProperty(rtpc::class, 'rawPacket'))->isInitialized($native), 'reading clone does not materialize source');
$restored = unserialize(serialize($native));
nativePacketCheck($restored->rawPacket === $base . $payload && $restored->payloadRaw === $native->payloadRaw, 'serialization retains original packet and changed payload');

$native = nativePacket($base . $payload);
$native->rawPacket = 'explicit replacement';
nativePacketCheck($native->rawPacket === 'explicit replacement', 'write before first read');
nativePacketCheck(unserialize(serialize($native))->rawPacket === 'explicit replacement', 'serialized replacement');
$native = nativePacket($base . $payload);
$rawReference = &$native->rawPacket;
nativePacketCheck($rawReference === $base . $payload, 'reference reads original packet');
$rawReference = 'changed by reference';
nativePacketCheck($native->rawPacket === 'changed by reference', 'reference updates property');
try {
    $rawReference = 42;
    throw new RuntimeException('reference lost string type');
} catch (TypeError) {
}
foreach ([new rtpc($base . $payload), nativePacket($base . $payload)] as $packet) {
    try {
        $packet->rawPacket = 42;
        throw new RuntimeException('direct write lost strict string type');
    } catch (TypeError) {
    }
    try {
        $unused = $packet->format;
        throw new RuntimeException('magic access exposed a private property');
    } catch (Error $error) {
        nativePacketCheck(str_contains($error->getMessage(), 'private property'), 'private visibility preserved');
    }
}
$native = nativePacket($base . $payload);
nativePacketCheck(($native->rawPacket ?? 'missing') === $base . $payload, 'null coalescing');
$clone = clone $native;
$clone->rawPacket = 'clone replacement';
nativePacketCheck($native->rawPacket === $base . $payload, 'ordinary read does not share clone writes');
$native = nativePacket($base . $payload);
$native->rawPacket .= 'suffix';
nativePacketCheck($native->rawPacket === $base . $payload . 'suffix', 'indirect write');
$native = nativePacket($base . $payload);
$traditional = new rtpc($base . $payload);
nativePacketCheck($native->build('replacement') === $traditional->build('replacement'), 'build after native parsing');
nativePacketCheck($native->rawPacket === $traditional->rawPacket, 'build replaces snapshot');
// The legacy parser deliberately includes RTP padding in payloadRaw.
$padded = pack('CCnNN', 0xa0, 8, 1, 2, 3) . "audio\0\0\3";
nativePacketCheck(nativePacket($padded)->payloadRaw === "audio\0\0\3", 'padding remains unchanged');

echo count($packets), " parser cases; snapshots, references, cloning, serialization and build OK\n";
