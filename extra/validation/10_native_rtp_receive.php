<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpc;
use libspech\Cache\cache;
use Swoole\Coroutine;

function nativeReceiveCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
}

final class NativeReceiveProbe extends SocketMutable
{
    public int $traditionalReads = 0;
    public int $nativeReads = 0;
    public ?WeakReference $buffer = null;
    public array $errors = [];

    public function recvfrom(mixed &$peername, float $timeout = 0): string|false
    {
        ++$this->traditionalReads;
        return parent::recvfrom($peername, $timeout);
    }

    public function recvfromInto(\Swoole\Buffer $buffer, mixed &$peername, float $timeout = 0): int|false
    {
        ++$this->nativeReads;
        nativeReceiveCheck($buffer->getCapacity() === 65536, 'native capacity');
        if ($this->buffer === null) {
            $this->buffer = WeakReference::create($buffer);
        } else {
            nativeReceiveCheck($this->buffer->get() === $buffer, 'receive buffer must be reused');
        }
        if ($this->errors !== []) {
            $this->errCode = array_shift($this->errors);
            return false;
        }
        return parent::recvfromInto($buffer, $peername, $timeout);
    }
}

function nativeReceiveCase(?bool $requested, bool $receiveOnly): array
{
    $rx = new NativeReceiveProbe(AF_INET, SOCK_DGRAM, SOL_UDP);
    $tx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $target = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $retired = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    foreach ([$rx, $tx, $target, $retired] as $socket) {
        nativeReceiveCheck($socket->bind('127.0.0.1', 0), 'bind');
    }
    $capable = function_exists('psampler_binary_slice')
        && class_exists('Swoole\\Buffer', false)
        && method_exists(ByteBuffer::class, 'supportsNativeBuffer')
        && ByteBuffer::supportsNativeBuffer(new Swoole\Buffer(1));
    $native = $requested === true && $capable;
    if ($native) $rx->errors = [SOCKET_EMSGSIZE, 110, 11];
    $media = new MediaChannel($rx, 'native-receive-validation');
    if ($requested !== null) $media->setNativeRtpReceive($requested);
    $media->setSettings(['sendSilenceProbeToMembers' => false]);
    $media->setReceiveOnlyMode($receiveOnly);
    $media->setAudioMetricsEnabled(true);
    foreach ([$tx, $target] as $socket) {
        $media->addMember(['address' => '127.0.0.1', 'port' => $socket->getsockname()['port'],
            'codec' => 'PCMA', 'pt' => 8, 'frequency' => 8000, 'channels' => 1, 'config' => []]);
    }
    $targetChannel = $media->members['127.0.0.1:' . $target->getsockname()['port']]['rtpChannel'];
    $targetChannel->sequenceNumber = 65535;
    $targetChannel->timestamp = 0xfffffff0;
    $targetChannel->ssrc = 0x12345678;
    (new ReflectionProperty($media, 'retiredMemberEndpoints'))->setValue($media,
        ['127.0.0.1:' . $retired->getsockname()['port'] => true]);
    $received = [];
    $done = new Swoole\Coroutine\Channel(1);
    $media->onReceive(static function (rtpc $packet, array $peer, MediaChannel $channel) use (&$received, $done, $native): void {
        nativeReceiveCheck((new ReflectionProperty(rtpc::class, 'rawPacket'))->isInitialized($packet) !== $native,
            'selected parser/materialization');
        $received[] = $packet;
        if (count($received) === 1) {
            // A synchronous callback may yield: no borrowed C view may survive.
            Coroutine::sleep(.001);
        }
        if (count($received) === 3) $done->push(true);
    });
    $payloads = [str_repeat("\xd5", 160), str_repeat("\x55", 160), str_repeat("\x7f", 160)];
    $packets = [];
    foreach ($payloads as $index => $payload) {
        $extra = $index === 1 ? pack('NNnn', 123, 456, 0xbede, 1) . 'ext!' : '';
        $packets[] = pack('CCnNN', $index === 1 ? 0x92 : 0x80, 8,
            (65535 + $index) & 0xffff, (0xfffffff0 + $index * 160) & 0xffffffff, 0x87654321) . $extra . $payload;
    }
    $media->start();
    $port = $rx->getsockname()['port'];
    $retired->sendto('127.0.0.1', $port, $packets[0]);
    foreach (['', '0', 'bad', pack('CCn', 0x80, 200, 0),
        pack('CCnNN', 0x40, 8, 1, 2, 3) . 'invalid version',
        pack('CCnNN', 0x80, 127, 1, 2, 3) . 'unknown PT', ...$packets] as $bytes) {
        nativeReceiveCheck($tx->sendto('127.0.0.1', $port, $bytes) === strlen($bytes), 'send');
    }
    nativeReceiveCheck($done->pop(2) === true, 'packet callbacks after recoverable errors');
    Coroutine::sleep(.005);
    $metrics = $media->getAudioMetrics();
    nativeReceiveCheck($metrics['total_packets'] === 3 && $metrics['rtcp_packets'] === 1, 'RTP/RTCP filters');
    nativeReceiveCheck($metrics['bytes_received'] === array_sum(array_map('strlen', $packets)), 'wire lengths');
    $outgoing = [];
    $peer = null;
    if (!$receiveOnly) {
        for ($index = 0; $index < 3; ++$index) {
            $outgoing[] = $target->recvfrom($peer, .1);
            nativeReceiveCheck(is_string($outgoing[$index]), 'encoded relay');
            nativeReceiveCheck((new rtpc($outgoing[$index]))->payloadRaw === $payloads[$index], 'relay payload');
        }
    }
    nativeReceiveCheck($target->recvfrom($peer, .002) === false, 'no extra packets');
    $media->close();
    Coroutine::sleep(.005);
    nativeReceiveCheck($rx->isClosed() && $media->eventSock->isClosed(), 'cancel/close');
    nativeReceiveCheck($native ? ($rx->nativeReads > 3 && $rx->traditionalReads === 0)
        : ($rx->traditionalReads > 0 && $rx->nativeReads === 0), 'mode selection/fallback');
    nativeReceiveCheck($rx->buffer === null || $rx->buffer->get() === null, 'native buffer released with receiver');
    foreach ($received as $index => $packet) {
        $packet->payloadRaw = 'consumer changed payload';
        nativeReceiveCheck($packet->rawPacket === $packets[$index], 'retained packet after receives and teardown');
    }
    foreach ([$tx, $target, $retired] as $socket) $socket->destroy();
    return $outgoing;
}

function nativeReceiveTranscode(bool $native, string $codec, int $channels): array
{
    $rate = $codec === 'L16' ? 44100 : 48000;
    $pt = $codec === 'L16' ? ($channels === 2 ? 10 : 11) : 111;
    $rx = new NativeReceiveProbe(AF_INET, SOCK_DGRAM, SOL_UDP);
    $tx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $target = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    foreach ([$rx, $tx, $target] as $socket) $socket->bind('127.0.0.1', 0);
    $media = new MediaChannel($rx, 'native-transcode-validation');
    $media->setNativeRtpReceive($native);
    $media->setSettings(['sendSilenceProbeToMembers' => false]);
    $media->addMember(['address' => '127.0.0.1', 'port' => $tx->getsockname()['port'],
        'codec' => $codec, 'pt' => $pt, 'frequency' => $rate, 'channels' => $channels, 'config' => []]);
    $media->addMember(['address' => '127.0.0.1', 'port' => $target->getsockname()['port'],
        'codec' => 'PCMA', 'pt' => 8, 'frequency' => 8000, 'channels' => 1, 'config' => []]);
    $targetChannel = $media->members['127.0.0.1:' . $target->getsockname()['port']]['rtpChannel'];
    $targetChannel->sequenceNumber = 123;
    $targetChannel->timestamp = 456;
    $targetChannel->ssrc = 789;
    $received = [];
    $media->onReceive(static function (rtpc $packet) use (&$received): void { $received[] = $packet; });
    $pcm = '';
    for ($sample = 0; $sample < (int)($rate * .020); ++$sample) {
        $pcm .= str_repeat(pack('v', (int)(sin($sample * 2 * M_PI * 437 / $rate) * 12000) & 0xffff), $channels);
    }
    $encoder = $codec === 'OPUS' ? new opusChannel($rate, $channels) : null;
    $media->start();
    $packets = [];
    for ($index = 0; $index < 3; ++$index) {
        $payload = $encoder === null ? encodePcmToL16($pcm) : $encoder->encode($pcm);
        $packets[] = pack('CCnNN', 0x80, $pt, $index, $index * (int)($rate * .020), 987) . $payload;
        $tx->sendto('127.0.0.1', $rx->getsockname()['port'], $packets[$index]);
    }
    Coroutine::sleep(.075);
    nativeReceiveCheck(count($received) === 3, "$codec/$channels input callbacks");
    $outgoing = [];
    $peer = null;
    while (($packet = $target->recvfrom($peer, .005)) !== false) {
        nativeReceiveCheck(strlen((new rtpc($packet))->payloadRaw) === 160, "$codec/$channels PCMA frame");
        $outgoing[] = $packet;
    }
    nativeReceiveCheck(count($outgoing) === 3, "$codec/$channels repacketized PCM frames");
    $media->close();
    Coroutine::sleep(.005);
    foreach ($received as $index => $packet) {
        nativeReceiveCheck($packet->rawPacket === $packets[$index], "$codec/$channels snapshot");
        if ($codec === 'L16') nativeReceiveCheck(decodeL16ToPcm($packet->payloadRaw) === $pcm, 'L16 endian/sample integrity');
    }
    $tx->destroy(); $target->destroy();
    return $outgoing;
}

function nativeReceiveIdle(bool $native): void
{
    $rx = new NativeReceiveProbe(AF_INET, SOCK_DGRAM, SOL_UDP);
    $tx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $rx->bind('127.0.0.1', 0);
    $media = new MediaChannel($rx, 'native-idle-validation');
    $media->setNativeRtpReceive($native);
    $media->setSettings(['sendSilenceProbeToMembers' => false]);
    $media->connectTimeout = 1;
    $done = new Swoole\Coroutine\Channel(1);
    $media->packetOnTimeout(static function () use ($done): void { $done->push(true); });
    $media->start();
    Coroutine::sleep(.55);
    $tx->sendto('127.0.0.1', $rx->getsockname()['port'], '');
    Coroutine::sleep(.55);
    $tx->sendto('127.0.0.1', $rx->getsockname()['port'], '');
    nativeReceiveCheck($done->pop(2) === true, 'inactivity callback');
    Coroutine::sleep(.001);
    nativeReceiveCheck($rx->traditionalReads + $rx->nativeReads === 2, 'empty datagrams must not refresh inactivity');
    nativeReceiveCheck($rx->isClosed() && $media->eventSock->isClosed(), 'inactivity closes sockets');
    $tx->destroy();
}

Swoole\Coroutine::set(['hook_flags' => 0]);
Swoole\Coroutine\run(static function (): void {
    cache::set('local_ip', '127.0.0.1');
    $initial = Coroutine::stats()['coroutine_num'];
    foreach ([false, true] as $receiveOnly) {
        $default = nativeReceiveCase(null, $receiveOnly);
        nativeReceiveCheck($default === nativeReceiveCase(false, $receiveOnly), 'default is traditional');
        nativeReceiveCheck($default === nativeReceiveCase(true, $receiveOnly), 'traditional/native wire parity');
    }
    foreach ([['L16', 1], ['L16', 2], ['OPUS', 2]] as [$codec, $channels]) {
        nativeReceiveCheck(nativeReceiveTranscode(false, $codec, $channels) === nativeReceiveTranscode(true, $codec, $channels),
            "$codec/$channels transcode and RTP timeline parity");
    }
    nativeReceiveIdle(false);
    nativeReceiveIdle(true);
    nativeReceiveCheck(Coroutine::stats()['coroutine_num'] === $initial, 'no receiver coroutines retained');
    echo "default, opt-in/fallback, buffer reuse, filters, relay, receive-only, L16/Opus mono/stereo transcoding, snapshots and teardown OK\n";
});
