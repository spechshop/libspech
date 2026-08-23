<?php

namespace libspech\Rtp;

use bcg729Channel;
use Closure;
use libspech\Cache\cache;
use libspech\Cli\cli;
use opusChannel;
use Swoole\Coroutine;
use Swoole\Coroutine\Socket;
use Throwable;
use function libspech\RtpRuntime\monoToStereo;
use function libspech\RtpRuntime\stereoToMono;
use function libspech\RtpRuntime\volumeAverage;


class MediaChannel
{
    public const DEFAULT_PACKET_TIME_MS = 20;

    public bool $active = true;

    public int $connectTimeout = 10;


    private int $packetTimeMs = self::DEFAULT_PACKET_TIME_MS;
    public bool $debugEnabled = false;
    private array $settings = [];
    private $lastVoiceActivity = 0;

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Define o ptime default do MediaChannel.
     *
     * Membros que não informaram ptime continuam acompanhando este valor. Membros
     * com ptime explícito preservam sua configuração individual.
     */
    public function setPacketTime(int $ptimeMs): void
    {
        if ($ptimeMs <= 0) {
            throw new \InvalidArgumentException('Packet time deve ser maior que 0');
        }

        $this->packetTimeMs = $ptimeMs;

        foreach ($this->rtpChans as $ssrc => $channel) {
            $memberId = $this->rtpChanMemberIds[$ssrc] ?? null;
            if ($memberId !== null && ($this->members[$memberId]['ptimeExplicit'] ?? false) === true) {
                continue;
            }
            if ($channel instanceof rtpChannel) {
                $channel->setPacketTime($ptimeMs);
            }
        }

        foreach ($this->members as $id => $member) {
            if (($member['ptimeExplicit'] ?? false) === true) {
                continue;
            }
            if (($member['rtpChannel'] ?? null) instanceof rtpChannel) {
                $this->members[$id]['rtpChannel']->setPacketTime($ptimeMs);
                $this->members[$id]['ptime'] = $ptimeMs;
                $this->members[$id]['samplesPerPacket'] = $this->members[$id]['rtpChannel']->samplesPerPacket;
                // Um residual criado com o frame anterior não pode ser reinterpretado
                // com outro tamanho de packetização.
                $this->members[$id]['pcmAccumulator'] = '';
            }
        }
    }

    public function getPacketTime(): int
    {
        return $this->packetTimeMs;
    }

    public function onReceive(callable $callback): void
    {
        $this->onReceiveCallable = $callback;
    }

    public function onDtmf(callable $callback): void
    {
        $this->onDtmfCallable = $callback;
    }

    public function onVadChange(callable $callback): void
    {
        $this->onVadChangeCallable = $callback;
    }

    /**
     * Habilita o sistema de adaptação automática
     */
    public function enableAdaptation(bool $useBuffer = true): void
    {
        $this->adaptationEnabled = true;
        if ($useBuffer) {
            $this->adaptiveBuffer->enable();
        }
    }

    /**
     * Desabilita o sistema de adaptação automática
     */
    public function disableAdaptation(): void
    {
        $this->adaptationEnabled = false;
        $this->adaptiveBuffer->disable();
    }

    public \SocketMutable $socket;


    /**
     * Array de membros com estrutura:
     * [
     *     'address' => string,
     *     'port' => int,
     *     'codec' => string,
     *     'pt' => int,
     *     'ptime' => int,
     *     'samplesPerPacket' => int,
     *     'pcmAccumulator' => string,
     *     'config' => array,
     *     'opusEncoder' => ?opusChannel,
     *     'opusDecoder' => ?opusChannel,
     *     'frequency' => int,
     *     'rtpChannel' => rtpChannel // owner de PT, sequence, timestamp e SSRC
     * ]
     *
     * @var array<string, array<string,mixed>>
     */
    public array $members = [];
    public int $defaultCodec = 8;
    public bcg729Channel $channelEncode;
    public bcg729Channel $channelDecode;
    public array $ptCodecs = [
        18 => 'G729', // G729
        101 => 'telephone-event', // DTMF
    ];
    public array $ptCodecsFrequency = [
        'G729' => 8000, // G729
        'telephone-event' => 8000, // DTMF
    ];
    /** @var array<int,int> RTP payload type => clock rate negotiated */
    public array $ptFrequencies = [];
    public ?opusChannel $opusChannel = null;
    public string $callId;
    public array $codecMapper = [];
    private ?\Swoole\Coroutine\Channel $blockChannel = null;
    public $onReceiveCallable = null;
    public $onDtmfCallable = null;
    public $onVadChangeCallable = null;
    public $onRecordingCallable = null;
    public bool $vadEnabled = false;
    public bool $isVoiceActive = false;
    private float $vadThreshold = 2.0;
    private int $vadHangoverFrames = 20;
    private int $vadCurrentHangover = 0;
    public bool $recordingEnabled = false;
    private string $recordingPath = '';
    private array $dtmfLastEvent = [];
    private int $dtmfDebounceMs = 100;
    private array $dtmfPacketCache = []; // Cache para detectar retransmissões RFC 4733
    private array $dtmfFiredGuard = []; // Guard temporal por (ssrc) para evitar disparo múltiplo do mesmo dígito
    public array $audioMetrics = [
        'total_packets' => 0,
        'lost_packets' => 0,
        'avg_energy' => 0.0,
        'voice_time' => 0.0,
        'silence_time' => 0.0,
        'rtcp_packets' => 0,
        'dtmf_events' => 0,
        'bytes_received' => 0,
        'packets_sent' => 0,
        'bytes_sent' => 0,
        'dropped_packets' => 0,
        'jitter' => 0.0,
        'first_arrival' => 0.0,
        'last_arrival' => 0.0,
        'max_seq_gap' => 0,
        'codecs' => [],
    ];
    private bool $audioMetricsEnabled = false;
    // Estado por ssrc para cálculo barato de perda/jitter (RFC 3550), sem funções pesadas
    private array $rtpStats = [];
    private AudioQualityDetector $qualityDetector;
    private AdaptiveBuffer $adaptiveBuffer;
    private bool $adaptationEnabled = false;
    private array $qualityReports = [];
    private int $adaptationCheckInterval = 50;
    private int $packetsProcessed = 0;
    public array $registeredIds = [];
    private array $lastVadActivity = [];
    private int $vadTimeoutSeconds = 10;
    private float $vadRegistrationThreshold = 2.0;
    private float $lastSilenceProbeAt = 0.0;
    public float $silenceProbeInterval = 0.5;

    /** @var array<string,bool> legs currently driven by an internal media source */
    private array $injectedLegs = [];


    public function block($callback = null): void
    {
        if ($callback) {
            $callback($this);
        }
        $this->blockChannel->pop();
    }

    public function unblock(): void
    {
        $this->active = false;
        if ($this->blockChannel->length() === 0) {
            $this->blockChannel->push(true);
        }
        $this->blockChannel->close();
    }

    public array $rtpChans = [];
    /** @var array<int,string> source SSRC => member id */
    private array $rtpChanMemberIds = [];
    /** @var array<string,string> */
    private array $legMemberIds = ['a' => '', 'b' => ''];
    public Socket $eventSock;
    public int $listenPort = 0;

    public function onDestruct(callable $callback):void {
        $this->onDestructCallable=$callback;
    }
    public function __destruct()
    {
        ($this->onDestructCallable)(...)();
    }
    private Closure $onDestructCallable;

    public function __construct(Socket|\SocketMutable &$socket, string $callId)
    {
        // A non-static closure created here is bound to $this and makes the
        // MediaChannel retain itself. That delays destruction of closed RTP
        // sockets until cyclic GC happens to run.
        $this->onDestructCallable = static function (): void {};

        $this->settings = [
            'sendSilenceProbeToMembers' => true,
        ];
        $this->socket = $socket;
        $this->callId = $callId;


        $this->channelEncode = new bcg729Channel();
        $this->channelDecode = new bcg729Channel();
        $this->adaptiveBuffer = new AdaptiveBuffer($this->callId);
        $this->blockChannel = new \Swoole\Coroutine\Channel(1);


        $this->adaptationEnabled = false;
        $this->qualityReports = [];
        $this->adaptationCheckInterval = 50;
        $this->packetsProcessed = 0;
        $this->eventSock = new \SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->rtpChans = [];
        $this->listenPort = $this->socket->getsockname()['port'];
    }

    public function resolveCodecNameFromPt(int $pt): ?string
    {
        if (isset($this->ptCodecs[$pt])) {
            return $this->ptCodecs[$pt];
        } elseif (in_array($pt, array_keys($this->codecMapper))) {
            return explode('/', $this->codecMapper[$pt])[0];
        } elseif ($pt === 0) {
            return "PCMU";
        } elseif ($pt === 1) {
            return "PCM";
        } elseif ($pt === 8) {
            return "PCMA";
        } elseif ($pt === 18) {
            return "G729";
        } elseif ($pt === 101) {
            return "telephone-event";
        }
        return "G729";
    }

    public function resolveFrequencyFromPt(int $pt): int
    {

        if (isset($this->ptFrequencies[$pt])) {
            return $this->ptFrequencies[$pt];
        } elseif (!empty($this->ptCodecsFrequency[$this->ptCodecs[$pt]])) {
            return $this->ptCodecsFrequency[$this->ptCodecs[$pt]];
        } elseif (in_array($pt, array_keys($this->codecMapper))) {
            return (int)explode('/', $this->codecMapper[$pt])[1] ?? 8000;
        } elseif ($pt === 0) {
            return 8000;
        } elseif ($pt === 1) {
            return 8000;
        } elseif ($pt === 8) {
            return 8000;
        }
        return 8000;
    }

    public function enableVAD(float $threshold = 2.0): void
    {
        $this->vadEnabled = true;
        $this->vadThreshold = $threshold;
    }

    /**
     * Define o valor mínimo de energia para registrar um ID pelo VAD
     */
    public function setVadRegistrationThreshold(float $threshold): void
    {
        $this->vadRegistrationThreshold = $threshold;
    }

    /**
     * Define o timeout em segundos para IDs registrados pelo VAD
     */
    public function setVadTimeout(int $timeoutSeconds): void
    {
        $this->vadTimeoutSeconds = $timeoutSeconds;
    }

    public array $openChannels = [];
    public int $portList = 0;
    public array $options = [];
    public int $retrys = 0;
    public mixed $ssrc = 0;

    private array $cacheKeys = [];

    public function generateDeterministicSsrc(string $ipPort): int
    {
        if (isset($this->cacheKeys[$ipPort])) {
            return $this->cacheKeys[$ipPort];
        }


        // Hash SHA-1 da string IP:porta (gera 40 caracteres hex)
        $hash = sha1($ipPort);

        // Pegar os primeiros 8 caracteres hex (32 bits)
        $hex = substr($hash, 0, 8);

        // Converter para inteiro (0 a 0xFFFFFFFF)
        $ssrc = (int)hexdec($hex);

        // Garantir que está dentro do range de 32 bits
        $result = $ssrc & 0xFFFFFFFF;
        if (!cache::exists('ssrcs')) cache::set('ssrcs', []);
        if (!in_array($result, cache::get('ssrcs'))) {
            cache::join('ssrcs', $result);
        }

        $this->cacheKeys[$ipPort] = $result;
        return $result;
    }


    public mixed $onStartCallable = false;

    public function onStart(callable $callable): void
    {
        $this->onStartCallable = $callable;
    }

    /**
     * Faz forward de pacotes DTMF (telephone-event) para todos os membros
     * Mantém o timestamp original do evento DTMF e ajusta o PT conforme necessário
     *
     * @param rtpc $rtpc Pacote RTP original com evento DTMF
     * @param array $peer Informações do peer de origem ['address' => string, 'port' => int]
     * @param string $idFrom Identificador do membro de origem (address:port)
     * @param array $destinationChannels Array de canais RTP por destino (passado por referência)
     */
    private function forwardDtmfToMembers(rtpc $rtpc, array $peer, string $idFrom): void
    {
        $payload = $rtpc->payloadRaw;

        if (strlen($payload) >= 4) {
            $event = ord($payload[0]);
            $e_r_volume = ord($payload[1]);
            $end = ($e_r_volume & 0x80) >> 7;
            $volume = $e_r_volume & 0x3F;

            $duration = (ord($payload[2]) << 8) | ord($payload[3]);
        } else {
            cli::pcl("Invalid DTMF payload length: " . strlen($payload), 'red');
            return;
        }


        foreach ($this->members as $targetId => $info) {
            if ($targetId === $idFrom) {
                continue;
            }
            $isFirstPacket = ($rtpc->marker === 1);
            if ($isFirstPacket) cli::pcl("DTMF: {$event} {$volume} {$duration} {$end} {$targetId}", 'bold_green');


            //


            $frequencyMember = $this->ptCodecsFrequency[$info['codec']] ?? 8000;

            // Construir e enviar pacote DTMF mantendo a timeline própria do canal de destino.
            // Usar o timestamp do remetente quebra a continuidade RTP no destino, gerando
            // "Jitter buffer empty / lost frames". Aqui o timestamp do evento é congelado na
            // timeline de áudio atual do canal e avançado pela duração ao final do evento.
            $this->relayDtmfPayloadForMember(
                $targetId,
                $rtpc,
                $event,
                $volume,
                $duration,
                $isFirstPacket,
                $end === 1
            );
        }
    }

    private function relayDtmfPayloadForMember(
        string $targetId,
        rtpc $sourcePacket,
        int $event,
        int $volume,
        int $duration,
        bool $isFirstPacket,
        bool $isEnd
    ): void {
        $member = $this->members[$targetId] ?? null;
        $channel = $member['rtpChannel'] ?? null;
        if (!is_array($member) || !$channel instanceof rtpChannel) {
            return;
        }

        $eventKey = $sourcePacket->ssrc . ':' . $sourcePacket->timestamp . ':' . $event;
        $state = $member['dtmfRelay'] ?? null;
        if ($isFirstPacket || !is_array($state) || ($state['eventKey'] ?? '') !== $eventKey) {
            $state = [
                'eventKey' => $eventKey,
                'lastProgressDuration' => 0,
                'emitted' => false,
                'endForwarded' => false,
            ];
        }

        // O campo duration recebido está no clock RFC4733. Para decidir quantos
        // pacotes o destino precisa, usamos o ptime individual dele nesse mesmo clock.
        $eventClockRate = max(1, $this->resolveFrequencyFromPt($sourcePacket->getCodec()));
        $durationStep = max(1, (int)round(($eventClockRate * $channel->packetTimeMs) / 1000));
        $nextDuration = (int)$state['lastProgressDuration'] + $durationStep;

        while ($nextDuration <= $duration) {
            $payload = pack('CCn', $event, $volume & 0x3F, $nextDuration);
            $packet = $channel->buildRelayedDtmfPacket($payload, !$state['emitted'], false, $nextDuration);
            $this->sendMediaPacket((string)$member['address'], (int)$member['port'], $packet);
            $state['emitted'] = true;
            $state['lastProgressDuration'] = $nextDuration;
            $nextDuration += $durationStep;
        }

        if ($isEnd) {
            // Retransmissões E recebidas continuam sendo encaminhadas, mas nunca
            // avançam novamente a timeline do destino.
            $payload = pack('CCn', $event, 0x80 | ($volume & 0x3F), $duration);
            $packet = $channel->buildRelayedDtmfPacket($payload, !$state['emitted'], true, $duration);
            $this->sendMediaPacket((string)$member['address'], (int)$member['port'], $packet);
            $state['emitted'] = true;
            if (!$state['endForwarded'] && $duration > $state['lastProgressDuration']) {
                $residualDuration = $duration - $state['lastProgressDuration'];
                $residualSamples = (int)round(($residualDuration * $channel->sampleRate) / $eventClockRate);
                $channel->advanceTimestampBySamples($residualSamples);
            }
            $state['endForwarded'] = true;
        }

        $this->members[$targetId]['dtmfRelay'] = $state;
    }

    private function isRtcpPacket(string $packet): bool
    {
        if (strlen($packet) < 4) {
            return false;
        }

        $version = (ord($packet[0]) >> 6) & 0x03;

        if ($version !== 2) {
            return false;
        }

        $type = ord($packet[1]);

        return $type >= 200 && $type <= 207;
    }

    public function start(): void
    {
        Coroutine::create(function () {
            try {
            $maxFrequency = 8000;
            $this->active = true;

            foreach ($this->ptCodecsFrequency as $codec => $frequency) {
                if ($frequency > $maxFrequency) {
                    $maxFrequency = $frequency;
                }
            }


            if (is_callable($this->onStartCallable)) go($this->onStartCallable, $this->callId);


            $lastPacketTime = microtime(true);
            $lastDebug = microtime(true);

            while (true) {
                if (!$this->active) {
                    // close() is normally called by the RTP control coroutine
                    // while recvfrom() belongs to this coroutine. Swoole may
                    // reject a cross-coroutine close, so the owner performs the
                    // definitive close after the 200 ms receive timeout wakes.
                    try {
                        if (method_exists($this->socket, 'destroy')) {
                            $this->socket->destroy();
                        } elseif (!$this->socket->isClosed()) {
                            $this->socket->close();
                        }
                    } catch (\Throwable) {
                    }
                    try {
                        if (method_exists($this->eventSock, 'destroy')) {
                            $this->eventSock->destroy();
                        } elseif (!$this->eventSock->isClosed()) {
                            $this->eventSock->close();
                        }
                    } catch (\Throwable) {
                    }
                    return;
                }
                $peer = ['address' => '0.0.0.0', 'port' => 0];
                $packet = $this->socket->recvfrom($peer, 0.2);
                $currentTime = microtime(true);


                if (!$packet) {
                    $now = $currentTime;
                    $elapsed = round($now - $lastPacketTime, 3);




                    $errCode = (int)($this->socket->errCode ?? 0);

                    if ($errCode !== 0 && !in_array($errCode, [110, 11, 35], true)) {
                        $this->unblock();
                        $this->socket->close();
                        $this->eventSock->close();

                        if ($this->active)
                            if (is_callable($this->packetOnTimeoutCallable)) {
                                call_user_func($this->packetOnTimeoutCallable, $this->callId);
                            }
                        return;
                    }

                    // Enquanto ainda não passou o timeout final, tenta acordar os members.
                    if ($elapsed <= $this->connectTimeout) {


                        // disabled

//                        if ($this->socket->getsockname()['port'] == $this->listenPort) {
//                            $try = $this->socket->getsockname()['port']-1;
//                            if (network::isPortAvailable($try, 'udp')) {
//                                cli::pcl("PORTA: $try disponivel", 'bold_green');
//                            } else {
//                                cli::pcl("PORTA: $try indisponivel", 'bold_red');
//                            }
//                            $this->socket->close();
//                            $this->socket = new \SocketMutable(AF_INET, SOCK_DGRAM, 0);
//                            if (!$this->socket->bind('0.0.0.0', (int)$try)) {
//                                cli::pcl("SOCKET ERROR: {$this->socket->errCode} {$this->socket->errMsg} PORTA: $try", 'bold_red');
//                            } else {
//                                cli::pcl("SOCKET BIND: {$this->socket->errCode} {$this->socket->errMsg} PORTA: " . $this->socket->getsockname()['port'], 'bold_green');
//                            }
//                        }

                        if ($this->settings['sendSilenceProbeToMembers']) $this->sendSilenceProbeToMembers($now);
                        continue;
                    }
                    if ($elapsed > $this->connectTimeout) {
                        // Agora sim: timeout real da chamada.
                        cli::pcl(
                            "TIMEOUT: no packets received for {$elapsed} seconds, exceed: {$this->connectTimeout}",
                            'bold_red'
                        );
                    }


                    $this->unblock();
                    $this->socket->close();
                    $this->eventSock->close();

                    if (is_callable($this->packetOnTimeoutCallable)) {
                        call_user_func($this->packetOnTimeoutCallable, $this->callId);
                    }

                    return;
                } else {
                    if ($peer['port'] === 5060) continue;
                    $lastPacketTime = microtime(true);
                }

                // RTP/RTCP needs at least the fixed header and media datagrams
                // larger than this are never produced by the supported codecs.
                // Keep an allocated session from becoming a generic UDP sink.
                $packetLength = strlen($packet);
                if ($packetLength < 12 || $packetLength > 4096) {
                    if ($this->audioMetricsEnabled) {
                        $this->audioMetrics['dropped_packets']++;
                    }
                    continue;
                }

                $idFrom = "{$peer['address']}:{$peer['port']}";
                if ($this->audioMetricsEnabled) {
                    $this->audioMetrics['total_packets']++;
                    $this->audioMetrics['bytes_received'] += strlen($packet);
                    if ($this->audioMetrics['first_arrival'] === 0.0) {
                        $this->audioMetrics['first_arrival'] = $currentTime;
                    }
                    $this->audioMetrics['last_arrival'] = $currentTime;
                }
                if ($this->isRtcpPacket($packet)) {
                    if ($this->audioMetricsEnabled) {
                        $this->audioMetrics['rtcp_packets']++;
                    }
                    continue;
                }
                $this->packetsProcessed++;


                $rtpc = new rtpc($packet);


                $pt = $rtpc->getCodec();


                $ssrcOrigin = $this->generateDeterministicSsrc($idFrom);
                $ssrc = $ssrcOrigin;


                if (!array_key_exists($rtpc->getCodec(), $this->ptCodecs)) {
                    $member = $this->members[$idFrom] ?? null;
                    if ($member) {
                        $this->ptCodecs[$rtpc->getCodec()] = $member['codec'] ?? $this->defaultCodec;
                    }
                }

                $codec = $this->resolveCodecNameFromPt($pt) ?? $pt;


                if ($this->audioMetricsEnabled) {
                    // Contagem por codec, perda e jitter (RFC 3550).
                    $this->audioMetrics['codecs'][$codec] = ($this->audioMetrics['codecs'][$codec] ?? 0) + 1;

                    $freqStat = (int)($this->ptCodecsFrequency[$codec] ?? 8000);
                    $arrivalTs = $currentTime * $freqStat;
                    if (isset($this->rtpStats[$ssrc])) {
                        $prev = $this->rtpStats[$ssrc];

                        // Perda estimada via lacuna no sequence number (wrap de 16 bits)
                        $expectedSeq = ($prev['seq'] + 1) & 0xFFFF;
                        $seqGap = ($rtpc->sequence - $expectedSeq) & 0xFFFF;
                        if ($seqGap > 0 && $seqGap < 1000) {
                            $this->audioMetrics['lost_packets'] += $seqGap;
                            if ($seqGap > $this->audioMetrics['max_seq_gap']) {
                                $this->audioMetrics['max_seq_gap'] = $seqGap;
                            }
                        }

                        // Jitter interarrival (RFC 3550): J += (|D| - J) / 16
                        $transit = $arrivalTs - $rtpc->timestamp;
                        $d = $transit - $prev['transit'];
                        if ($d < 0) $d = -$d;
                        $this->audioMetrics['jitter'] += ($d - $this->audioMetrics['jitter']) / 16;

                        $this->rtpStats[$ssrc]['seq'] = $rtpc->sequence;
                        $this->rtpStats[$ssrc]['transit'] = $transit;
                    } else {
                        $this->rtpStats[$ssrc] = [
                            'seq' => $rtpc->sequence,
                            'transit' => $arrivalTs - $rtpc->timestamp,
                        ];
                    }
                }


                if (!array_key_exists($ssrc, $this->rtpChans)) {
                    $sourceMember = $this->members[$idFrom] ?? null;
                    $sourcePtime = ($sourceMember['rtpChannel'] ?? null) instanceof rtpChannel
                        ? $sourceMember['rtpChannel']->packetTimeMs
                        : (int)($sourceMember['ptime'] ?? $this->packetTimeMs);
                    $this->rtpChans[$ssrc] = new rtpChannel($rtpc->getCodec(), $this->ptCodecsFrequency[$codec] ?? 8000, $sourcePtime, $ssrc);
                    $this->rtpChanMemberIds[$ssrc] = $idFrom;
                    $this->rtpChans[$ssrc]->sequenceNumber = $rtpc->sequence++;
                    $this->rtpChans[$ssrc]->timestamp = $rtpc->timestamp;
                    $this->rtpChans[$ssrc]->bcg729Channel = new bcg729Channel();
                }

                $pcmData = false;




                if (!array_key_exists($rtpc->getCodec(), $this->ptCodecs)) {
                    $member = $this->members[$idFrom] ?? null;
                    if ($member) {
                        $this->ptCodecs[$rtpc->getCodec()] = $member['codec'] ?? $this->defaultCodec;
                    }
                }

                $pt = $rtpc->getCodec();


                if (strtolower($codec) === 'telephone-event') {
                    // Unknown sources never get to use an allocated session as
                    // an RTP/DTMF relay. Comedia learning is driven by audio and
                    // must first rebind a configured leg to this endpoint.
                    if (!$this->isMember($idFrom)) {
                        continue;
                    }
                    //cli::pcl("$idFrom TELEPHONE-EVENT  " . time(), 'yellow');
                    if ($this->audioMetricsEnabled) {
                        $this->audioMetrics['dtmf_events']++;
                    }
                    $this->forwardDtmfToMembers($rtpc, $peer, $idFrom);


                    $this->processDtmf($rtpc, $peer, function ()   {

                    });

                    continue;
                }

                if ($this->onReceiveCallable) {
                    // This callback only updates in-memory session state and may
                    // enqueue a coalesced maintenance mark. Spawning one
                    // coroutine for every RTP packet adds scheduler pressure and
                    // keeps MediaChannel/RtpSession references alive during
                    // teardown. Run it inline with the receive coroutine.
                    ($this->onReceiveCallable)($rtpc, $peer, $this, $this->rtpChans[$ssrc]);
                }

                // The callback may have confirmed an A-leg comedia candidate
                // and atomically rebound its configured member. Until then the
                // packet is observed but never forwarded to any destination.
                if (!$this->isMember($idFrom)) {
                    continue;
                }

                try {
                    $pcmData = match (strtoupper($codec)) {
                        'G729' => $this->rtpChans[$ssrc]->bcg729Channel->decode($rtpc->payloadRaw),
                        'PCMU' => decodePcmuToPcm($rtpc->payloadRaw),
                        'PCMA' => decodePcmaToPcm($rtpc->payloadRaw),
                        'OPUS' => ($this->members[$idFrom]['opusDecoder'] ?? $this->members[$idFrom]['opus'])->decode($rtpc->payloadRaw),
                        'L16' => decodeL16ToPcm($rtpc->payloadRaw),
                        default => false
                    };
                } catch (Throwable $e) {
                    continue;
                }
                if ($pcmData === false) continue;
                if ($this->vadEnabled) {
                    $currentTime = microtime(true);

                    if (empty($this->lastVoiceActivity)) {
                        $this->lastVoiceActivity = $currentTime;
                    }

                    $frequency = $this->members[$idFrom]['frequency'] ?? 8000;
                    $volume = volumeAverage($pcmData, $frequency);

                    if ($volume > 1) {
                        $this->lastVoiceActivity = $currentTime;
                    }

                    $diff = round($currentTime - $this->lastVoiceActivity, 2);


                    if ($diff >= $this->vadTimeoutSeconds) {
                        cli::pcl(
                            "VAD: {$idFrom} desativado por timeout de {$this->vadTimeoutSeconds}s após {$diff}s de silêncio",
                            'red'
                        );

                        $this->close();
                        return;
                    }
                }


                $sourceCodec = strtoupper((string)$codec);
                $sourceMember = $this->members[$idFrom] ?? [];
                $sourceFrequency = (int)($sourceMember['frequency'] ?? $this->ptCodecsFrequency[$sourceCodec] ?? $this->resolveFrequencyFromPt($pt) ?? 8000);
                if ($sourceFrequency <= 0) $sourceFrequency = 8000;

                $sourceChannels = (int)($sourceMember['channels'] ?? $this->ptCodecsChannels[$pt] ?? 1);
                if ($sourceChannels <= 0) $sourceChannels = 1;

                $sourcePcmData = $pcmData;
                if ($this->vadEnabled) {
                    $this->processVAD($sourcePcmData, $idFrom, $sourceFrequency, $sourceChannels);
                }

                foreach ($this->members as $targetId => $info) {
                    if ($targetId === $idFrom) continue;

                    // Durante o envio de DTMF (RFC 4733) o relay de áudio é suspenso
                    // para não sobrepor pacotes de áudio aos pacotes telephone-event
                    // na mesma SSRC, o que causava chiado, cortes e perda de pacotes.
                    if ($this->dtmfInUse) {
                        continue;
                    }

                    try {
                        // A repacketização sempre ocorre em PCM16LE, depois da
                        // conversão de frequência/canais e antes do encoder do destino.
                        $pcmForTarget = $this->convertPcmForMember(
                            $targetId,
                            $sourcePcmData,
                            $sourceFrequency,
                            $sourceChannels
                        );
                        $this->queuePcmForMember($targetId, $pcmForTarget);
                    } catch (Throwable $e) {
                        if ($this->debugEnabled) {
                            $targetCodec = strtoupper((string)($info['codec'] ?? ''));
                            cli::pcl("{$this->callId} MediaChannel transcode {$sourceCodec}->{$targetCodec}: {$e->getMessage()}", 'red');
                        }
                    }
                }
                if ($this->debugEnabled) {
                    if (empty($lastDebug)) $lastDebug = microtime(true);
                    if (microtime(true) - $lastDebug >= 0.160) {


                        $timeMS = round((microtime(true) - $lastPacketTime) * 1000, 2);
                        cli::pcl("$this->callId MediaChannel: " . $timeMS . "ms com " . count($this->members) . " membros",
                            !empty($packet) ? 'bold_green' : 'bold_red'
                        );
                        $lastDebug = microtime(true);

                    }
                }
            }
            } finally {
                $this->active = false;
                try {
                    if (method_exists($this->socket, 'destroy')) {
                        $this->socket->destroy();
                    } elseif (!$this->socket->isClosed()) {
                        $this->socket->close();
                    }
                } catch (\Throwable) {
                }
                try {
                    if (method_exists($this->eventSock, 'destroy')) {
                        $this->eventSock->destroy();
                    } elseif (!$this->eventSock->isClosed()) {
                        $this->eventSock->close();
                    }
                } catch (\Throwable) {
                }
            }
        });
    }

    public function isMember(string $id): bool
    {
        return isset($this->members[$id]);
    }

    public function addMember(array $peer): void
    {
        $peer['config'] = is_array($peer['config'] ?? null) ? $peer['config'] : [];
        if (isset($peer['channels'])) {
            $nc = max(1, (int)$peer['channels']);
        } else {
            $stereo = $peer['config']['stereo'] ?? $this->options['stereo'] ?? false;
            $nc = $stereo ? 2 : 1;
        }
        $codec = strtoupper((string)($peer['codec'] ?? ''));
        if (in_array($codec, ['PCMA', 'PCMU', 'G729'], true)) {
            $peer['frequency'] = 8000;
            $nc = 1;
        }

        $ptimeExplicit = array_key_exists('ptime', $peer) && $peer['ptime'] !== null;
        $ptimeMs = $ptimeExplicit ? (int)$peer['ptime'] : $this->packetTimeMs;
        if ($ptimeMs <= 0) {
            throw new \InvalidArgumentException('Packet time do membro deve ser maior que 0');
        }

        // Encoder e decoder Opus separados evitam compartilhar estado entre as duas
        // direções do mesmo membro. `opus` permanece como alias legado do decoder.
        $peer['opusEncoder'] = new opusChannel(48000, $nc);
        $peer['opusDecoder'] = new opusChannel(48000, $nc);
        $peer['opus'] = $peer['opusDecoder'];
        $id = "{$peer['address']}:{$peer['port']}";

        if (!empty($peer['config'])) {
            if (!empty($peer['config'][(int)$peer['pt']])) {
                if (!empty($peer['config']['maxaveragebitrate'])) {
                    foreach ([$peer['opusEncoder'], $peer['opusDecoder']] as $opus) {
                        $opus->setBitrate((int)$peer['config']['maxaveragebitrate']);
                    }
                } elseif (!empty($peer['config']['maxplaybackrate'])) {
                    foreach ([$peer['opusEncoder'], $peer['opusDecoder']] as $opus) {
                        $opus->setBitrate((int)$peer['config']['maxplaybackrate']);
                    }
                }

                $config = $peer['config'];
                foreach ([$peer['opusEncoder'], $peer['opusDecoder']] as $opus) {
                    if (!empty($config['userdtx'])) $opus->setDTX(true);
                    if (!empty($config['cbr'])) $opus->setVBR(true);
                    $opus->setComplexity(8);
                    $opus->setSignalVoice(true);
                    $opus->setDTX(true);
                    $opus->setVBR(true);
                }
            }
        }
        foreach ([$peer['opusEncoder'], $peer['opusDecoder']] as $opus) {
            $opus->setBitrate($peer['config']['maxplaybackrate'] ?? 24000);
        }

        $peer['rtpChannel'] = new rtpChannel((int)$peer['pt'], (int)$peer['frequency'], $ptimeMs, $this->generateDeterministicSsrc($id));
        $peer['rtpChannel']->setSsrc($this->generateDeterministicSsrc($id));
        $peer['ptime'] = $ptimeMs;
        $peer['ptimeExplicit'] = $ptimeExplicit;
        $peer['samplesPerPacket'] = $peer['rtpChannel']->samplesPerPacket;
        $peer['pcmAccumulator'] = '';
        $peer['channels'] = $nc;
        if ($codec === 'G729') {
            $peer['bcg729Channel'] = new bcg729Channel();
        }
        $this->ptCodecsChannels[$peer['pt']] = $nc;
        $this->ptFrequencies[$peer['pt']] = (int)$peer['frequency'];
        $this->members[$id] = $peer;
        if (in_array((string)($peer['leg'] ?? ''), ['a', 'b'], true)) {
            $this->legMemberIds[(string)$peer['leg']] = $id;
        }
    }

    private function processVAD(string $pcmData, ...$extra): void
    {
        if (!$this->vadEnabled) {
            return;
        }
        $energy = volumeAverage($pcmData);
        $idFrom = $extra[0] ?? $this->callId;
        $frequency = (int)($extra[1] ?? $this->members[$idFrom]['frequency'] ?? 0);
        $channels = (int)($extra[2] ?? $this->members[$idFrom]['channels'] ?? 0);
        $frameDuration = $this->packetTimeMs / 1000;
        if ($frequency > 0 && $channels > 0 && (strlen($pcmData) % (2 * $channels)) === 0) {
            $frameDuration = (strlen($pcmData) / (2 * $channels)) / $frequency;
        }
        if ($this->audioMetricsEnabled) {
            $this->audioMetrics['avg_energy'] = $this->audioMetrics['avg_energy'] * 0.9 + $energy * 0.1;
        }
        $wasActive = $this->isVoiceActive;
        if ($energy > $this->vadRegistrationThreshold) {
            if (!isset($this->registeredIds[$idFrom])) {
                $this->registeredIds[$idFrom] = true;
            }
            $this->lastVadActivity[$idFrom] = microtime(true);
        }
        if ($energy > $this->vadThreshold) {
            $this->isVoiceActive = true;
            // Mantém os 400 ms legados (20 frames de 20 ms) mesmo quando a
            // origem usa outro ptime ou entrega PCM com duração diferente.
            $legacyHangoverSeconds = ($this->vadHangoverFrames * self::DEFAULT_PACKET_TIME_MS) / 1000;
            $this->vadCurrentHangover = max(1, (int)ceil($legacyHangoverSeconds / $frameDuration));
        } else if ($this->vadCurrentHangover > 0) {
            $this->vadCurrentHangover--;
            $this->isVoiceActive = true;
        } else {
            $this->isVoiceActive = false;
        }
        if ($wasActive !== $this->isVoiceActive) {
            if (is_callable($this->onVadChangeCallable)) {
                go($this->onVadChangeCallable, $this->isVoiceActive, $energy, $extra[0]);
            }
        }
        if ($this->audioMetricsEnabled) {
            if ($this->isVoiceActive) {
                $this->audioMetrics['voice_time'] += $frameDuration;
            } else {
                $this->audioMetrics['silence_time'] += $frameDuration;
            }
        }
    }

    /**
     * Habilita ou desabilita a coleta de métricas de áudio.
     *
     * Ao desabilitar, os valores já coletados são preservados e o estado
     * temporário de perda/jitter é liberado. Ao reabilitar, perda e jitter
     * voltam a ser calculados a partir do próximo pacote RTP.
     */
    public function setAudioMetricsEnabled(bool $enabled): void
    {
        if ($this->audioMetricsEnabled === $enabled) {
            return;
        }

        $this->audioMetricsEnabled = $enabled;
        $this->rtpStats = [];
    }

    public function getAudioMetrics(): array
    {
        return $this->audioMetrics;
    }

    public bool $dtmfInUse = false;

    public function send2833(string $digit): void
    {
        if ($this->socket->isClosed()) {
            return;
        }

        if (empty($this->members)) {
            return;
        }

        $event = match (strtoupper($digit)) {
            '0' => 0,
            '1' => 1,
            '2' => 2,
            '3' => 3,
            '4' => 4,
            '5' => 5,
            '6' => 6,
            '7' => 7,
            '8' => 8,
            '9' => 9,
            '*' => 10,
            '#' => 11,
            'A' => 12,
            'B' => 13,
            'C' => 14,
            'D' => 15,
            default => null,
        };

        if ($event === null) {
            cli::pcl("[DTMF] Dígito inválido: {$digit}", "bold_red");
            return;
        }

        // Snapshot das chaves dos membros para iterar de forma estável.
        $memberKeys = array_keys($this->members);

        // Envio SÍNCRONO (sem coroutine separada): garante que múltiplos dígitos
        // enviados em sequência cheguem na ORDEM correta. Os Coroutine::sleep()
        // abaixo apenas cedem o controle para o agendador (o loop de mídia roda
        // em outra coroutine), portanto não bloqueiam o áudio. Enquanto o tom é
        // transmitido, a flag dtmfInUse suspende o relay de áudio para não
        // sobrepor pacotes na mesma SSRC (causa de chiado e perda de pacotes).
        try {
            $this->dtmfInUse = true;

            // RFC 4733: volume=10 e duração total de 160ms (1280 samples@8kHz).
            // O intervalo e o avanço por pacote seguem o ptime de cada destino.
            // 160ms é mais compatível com a maioria dos endpoints SIP/PJSIP e evita
            // que o evento se arraste por tempo demais em relação a outros clientes.
            $volume = 10;
            $endRetransmits = 3;
            $durationMs = 160;

            foreach ($memberKeys as $key) {
                $member = $this->members[$key] ?? null;
                if ($member === null) {
                    continue;
                }

                $ip = $member['address'] ?? null;
                $port = $member['port'] ?? null;
                if (empty($ip) || empty($port)) {
                    continue;
                }

                $rtpChannel = $member['rtpChannel'] ?? null;
                if (!$rtpChannel instanceof rtpChannel) {
                    continue;
                }

                $ptimeMs = $rtpChannel->packetTimeMs;
                $memberFrequency = max(1, (int)($member['frequency'] ?? $rtpChannel->sampleRate));
                $ptTelephoneEvent = $this->findTelephoneEventPt($memberFrequency);
                $eventClockRate = (int)($this->ptFrequencies[$ptTelephoneEvent] ?? 8000);
                $finalDurationSamples = max(1, (int)round(($eventClockRate * $durationMs) / 1000));
                $stepSamples = max(1, (int)round(($eventClockRate * $ptimeMs) / 1000));
                $steps = max(1, (int)ceil($finalDurationSamples / $stepSamples));
                $rtpChannel->setNewPtDTMF($ptTelephoneEvent);

                // Timestamp do evento fixo em todos os pacotes do mesmo dígito (RFC 4733)
                $eventTs = (int)$rtpChannel->timestamp;

                // Pacotes de progresso do evento
                for ($i = 1; $i <= $steps; $i++) {
                    if ($this->socket->isClosed()) {
                        return;
                    }

                    $duration = $i * $stepSamples;
                    if ($duration > $finalDurationSamples) {
                        $duration = $finalDurationSamples;
                    }

                    $isFirst = ($i === 1);
                    $isLast = ($duration >= $finalDurationSamples);

                    // Byte 2 do payload: bit 7 = E (não setar aqui); bits 0..5 = volume
                    $payload = pack('CCn', $event, $volume & 0x3F, $duration);

                    $packet = $rtpChannel->buildDtmfForwardPacket($payload, $eventTs, $isFirst);
                    $this->sendMediaPacket((string)$ip, (int)$port, $packet);

                    // Dorme entre os pacotes, exceto depois do último "progresso"
                    if (!$isLast) {
                        Coroutine::sleep($ptimeMs / 1000);
                    }
                }

                // Retransmite o último pacote com E-bit 3 vezes
                $payloadEnd = pack('CCn', $event, 0x80 | ($volume & 0x3F), $finalDurationSamples);

                for ($r = 0; $r < $endRetransmits; $r++) {
                    if ($this->socket->isClosed()) {
                        return;
                    }

                    $packet = $rtpChannel->buildDtmfForwardPacket($payloadEnd, $eventTs, false);
                    $this->sendMediaPacket((string)$ip, (int)$port, $packet);

                    if ($r < $endRetransmits - 1) {
                        Coroutine::sleep($ptimeMs / 1000);
                    }
                }

                // Mantém a timeline de áudio contínua após o evento DTMF
                $audioDurationSamples = (int)round(($rtpChannel->sampleRate * $durationMs) / 1000);
                $rtpChannel->advanceTimestampBySamples($audioDurationSamples);
            }
        } catch (\Throwable $e) {
            // silencioso: não interrompe o fluxo de áudio da chamada
        } finally {
            $this->dtmfInUse = false;
        }
    }

    private function checkVadTimeouts(): bool
    {
        $currentTime = microtime(true);
        foreach ($this->registeredIds as $idFrom => $registered) {
            if (!$registered) {
                continue;
            }
            $lastActivity = $this->lastVadActivity[$idFrom] ?? 0;
            if ($currentTime - $lastActivity > $this->vadTimeoutSeconds) {
                return true;
            }
        }
        return false;
    }

    private function sendSilenceProbeToMembers(float $currentTime): void
    {
        if (empty($this->members)) {
            return;
        }

        if (($currentTime - $this->lastSilenceProbeAt) < $this->silenceProbeInterval) {
            return;
        }

        $this->lastSilenceProbeAt = $currentTime;

        foreach ($this->members as $idMember => $member) {
            $memberLeg = strtolower((string)($member['leg'] ?? ''));
            if ($memberLeg !== '' && isset($this->injectedLegs[$memberLeg])) {
                continue;
            }
            if (empty($member['address']) || empty($member['port'])) {
                continue;
            }

            if (!isset($member['rtpChannel'])) {
                continue;
            }

            try {
                $this->queuePcmForMember($idMember, $this->makeSilencePcmForMember($idMember));
            } catch (\Throwable $e) {
            }
        }
    }

    public function setLegInjectionActive(string $leg, bool $active): void
    {
        $leg = strtolower(trim($leg));
        if (!in_array($leg, ['a', 'b'], true)) {
            return;
        }
        if ($active) {
            $this->injectedLegs[$leg] = true;
            foreach ($this->members as $member) {
                if (strtolower((string)($member['leg'] ?? '')) === $leg && isset($member['rtpChannel'])) {
                    $member['rtpChannel']->setMarkerBit(true);
                }
            }
        } else {
            unset($this->injectedLegs[$leg]);
        }
    }

    /**
     * Injects arbitrary signed 16-bit little-endian PCM into a negotiated leg.
     * The caller does not need to slice frames: incomplete PCM is accumulated and
     * oversized PCM emits every complete RTP allowed by the member's ptime.
     * Encoding and RTP state stay owned by the member's existing rtpChannel.
     *
     * @return array{codec:string,payload_type:int,frequency:int,sequence:int,timestamp:int,ssrc:int}|null
     */
    public function sendPcmToLeg(string $leg, string $pcm, int $sourceFrequency, int $sourceChannels = 1): ?array
    {
        $leg = strtolower(trim($leg));
        if (!$this->active || !in_array($leg, ['a', 'b'], true) || $pcm === '' || $sourceFrequency <= 0) {
            return null;
        }

        $canonicalId = $this->legMemberIds[$leg] ?? '';
        if ($canonicalId !== '' && isset($this->members[$canonicalId])) {
            $member = $this->members[$canonicalId];
            if (isset($member['rtpChannel'])) {
                return $this->sendPcmToMember($canonicalId, $member, $pcm, $sourceFrequency, $sourceChannels);
            }
        }

        foreach ($this->members as $id => $member) {
            if (strtolower((string)($member['leg'] ?? '')) !== $leg || !isset($member['rtpChannel'])) {
                continue;
            }
            return $this->sendPcmToMember($id, $member, $pcm, $sourceFrequency, $sourceChannels);
        }
        return null;
    }

    /**
     * Converte, acumula e envia somente frames completos no ptime do membro.
     * Retorna os metadados do último RTP enviado ou null quando restou apenas PCM
     * parcial no accumulator.
     *
     * @param array<string,mixed> $member
     * @return array{codec:string,payload_type:int,frequency:int,sequence:int,timestamp:int,ssrc:int}|null
     */
    private function sendPcmToMember(string $id, array $member, string $pcm, int $sourceFrequency, int $sourceChannels): ?array
    {
        $converted = $this->convertPcmForMember($id, $pcm, $sourceFrequency, $sourceChannels);
        $sent = $this->queuePcmForMember($id, $converted);
        return empty($sent) ? null : $sent[array_key_last($sent)];
    }

    /** @return array{frequency:int,channels:int} */
    private function pcmFormatForMember(string $id): array
    {
        $member = $this->members[$id] ?? null;
        if (!is_array($member)) {
            throw new \RuntimeException('playback_member_not_found');
        }

        $codec = strtoupper((string)($member['codec'] ?? ''));
        $frequency = max(1, (int)($member['frequency'] ?? 8000));
        $channels = max(1, (int)($member['channels'] ?? 1));

        if (in_array($codec, ['PCMA', 'PCMU', 'G729'], true)) {
            $frequency = 8000;
            $channels = 1;
        }

        return ['frequency' => $frequency, 'channels' => $channels];
    }

    private function convertPcmForMember(string $id, string $pcm, int $sourceFrequency, int $sourceChannels): string
    {
        if ($pcm === '' || $sourceFrequency <= 0 || $sourceChannels <= 0) {
            throw new \RuntimeException('playback_pcm_invalid');
        }
        if ((strlen($pcm) % (2 * $sourceChannels)) !== 0) {
            throw new \RuntimeException('playback_pcm_alignment_invalid');
        }

        $format = $this->pcmFormatForMember($id);
        $converted = $pcm;
        $pcmChannels = $sourceChannels;

        if ($pcmChannels === 2 && $format['channels'] === 1) {
            $converted = stereoToMono($converted);
            $pcmChannels = 1;
        } elseif ($pcmChannels === 1 && $format['channels'] === 2) {
            $converted = monoToStereo($converted);
            $pcmChannels = 2;
        }

        if ($pcmChannels !== $format['channels']) {
            throw new \RuntimeException('playback_channels_not_supported');
        }

        if ($sourceFrequency !== $format['frequency']) {
            // O accumulator é sempre PCM16LE. L16 só vira big-endian no encoder.
            $converted = resampler($converted, $sourceFrequency, $format['frequency'], false);
        }

        return $converted;
    }

    /**
     * @return list<array{codec:string,payload_type:int,frequency:int,sequence:int,timestamp:int,ssrc:int}>
     */
    private function queuePcmForMember(string $id, string $pcm): array
    {
        $member = $this->members[$id] ?? null;
        $channel = $member['rtpChannel'] ?? null;
        if (!is_array($member) || !$channel instanceof rtpChannel) {
            throw new \RuntimeException('playback_rtp_channel_not_found');
        }

        $format = $this->pcmFormatForMember($id);
        $frameBytes = $channel->samplesPerPacket * $format['channels'] * 2;
        if ($frameBytes <= 0) {
            throw new \RuntimeException('playback_frame_size_invalid');
        }

        $this->members[$id]['ptime'] = $channel->packetTimeMs;
        $this->members[$id]['samplesPerPacket'] = $channel->samplesPerPacket;
        $this->members[$id]['pcmAccumulator'] = (string)($member['pcmAccumulator'] ?? '') . $pcm;

        $sent = [];
        while (strlen($this->members[$id]['pcmAccumulator']) >= $frameBytes) {
            $frame = substr($this->members[$id]['pcmAccumulator'], 0, $frameBytes);
            $this->members[$id]['pcmAccumulator'] = substr($this->members[$id]['pcmAccumulator'], $frameBytes);

            $payload = $this->encodePcmFrameForMember($id, $frame);
            if ($payload === '') {
                throw new \RuntimeException('playback_encode_failed');
            }

            $sequence = (int)$channel->sequenceNumber;
            $timestamp = (int)$channel->timestamp;
            $packet = $channel->buildAudioPacket($payload);
            $this->sendMediaPacket((string)$member['address'], (int)$member['port'], $packet);
            $sent[] = [
                'codec' => strtoupper((string)($member['codec'] ?? '')),
                'payload_type' => (int)$channel->payloadType,
                'frequency' => (int)$channel->sampleRate,
                'sequence' => $sequence,
                'timestamp' => $timestamp,
                'ssrc' => (int)$channel->ssrc,
            ];
        }

        return $sent;
    }

    private function encodePcmFrameForMember(string $id, string $pcmFrame): string
    {
        $codec = strtoupper((string)($this->members[$id]['codec'] ?? ''));

        return match ($codec) {
            'PCMA' => encodePcmToPcma($pcmFrame),
            'PCMU' => encodePcmToPcmu($pcmFrame),
            'L16' => encodePcmToL16($pcmFrame),
            'PCM' => $pcmFrame,
            'OPUS' => $this->encodeOpusFrameForMember($id, $pcmFrame),
            'G729' => $this->encodeG729FrameForMember($id, $pcmFrame),
            default => throw new \RuntimeException('playback_codec_not_supported'),
        };
    }

    private function encodeOpusFrameForMember(string $id, string $pcmFrame): string
    {
        $encoder = $this->members[$id]['opusEncoder'] ?? $this->members[$id]['opus'] ?? null;
        if (!$encoder instanceof opusChannel) {
            throw new \RuntimeException('playback_opus_encoder_not_found');
        }
        return $encoder->encode($pcmFrame);
    }

    private function encodeG729FrameForMember(string $id, string $pcmFrame): string
    {
        if (!isset($this->members[$id]['bcg729Channel']) || !$this->members[$id]['bcg729Channel'] instanceof bcg729Channel) {
            $this->members[$id]['bcg729Channel'] = new bcg729Channel();
        }
        if ((strlen($pcmFrame) % 160) !== 0) {
            throw new \RuntimeException('playback_g729_frame_invalid');
        }

        $payload = '';
        for ($offset = 0; $offset < strlen($pcmFrame); $offset += 160) {
            $payload .= $this->members[$id]['bcg729Channel']->encode(substr($pcmFrame, $offset, 160));
        }
        return $payload;
    }

    /** @return array<string,mixed>|null */
    public function memberByLeg(string $leg): ?array
    {
        $leg = strtolower(trim($leg));
        if (!in_array($leg, ['a', 'b'], true)) {
            return null;
        }

        $canonicalId = $this->legMemberIds[$leg] ?? '';
        if ($canonicalId !== '' && isset($this->members[$canonicalId])) {
            return $this->members[$canonicalId];
        }

        foreach ($this->members as $id => $member) {
            if (strtolower((string)($member['leg'] ?? '')) === $leg) {
                $this->legMemberIds[$leg] = $id;
                return $member;
            }
        }

        return null;
    }

    public function memberIdByLeg(string $leg): ?string
    {
        $leg = strtolower(trim($leg));
        if (!in_array($leg, ['a', 'b'], true)) {
            return null;
        }
        $this->memberByLeg($leg);
        $id = $this->legMemberIds[$leg] ?? '';
        return $id !== '' && isset($this->members[$id]) ? $id : null;
    }

    public function removeMemberByLeg(string $leg): bool
    {
        $id = $this->memberIdByLeg($leg);
        if ($id === null) {
            return false;
        }
        unset($this->members[$id], $this->registeredIds[$id], $this->lastVadActivity[$id]);
        $this->legMemberIds[strtolower($leg)] = '';
        foreach ($this->rtpChanMemberIds as $ssrc => $memberId) {
            if ($memberId !== $id) continue;
            unset($this->rtpChanMemberIds[$ssrc], $this->rtpChans[$ssrc], $this->rtpStats[$ssrc]);
        }
        return true;
    }

    private function sendMediaPacket(string $address, int $port, string $packet): bool
    {
        $sent = $this->socket->sendto($address, $port, $packet);
        if ($sent !== false && $this->audioMetricsEnabled) {
            $this->audioMetrics['packets_sent']++;
            $this->audioMetrics['bytes_sent'] += strlen($packet);
        }
        return $sent !== false;
    }

    public function rebindMemberTransport(string $leg, string $address, int $port): bool
    {
        $leg = strtolower(trim($leg));
        $address = trim($address);
        if (!in_array($leg, ['a', 'b'], true) || $address === '' || $port <= 0) {
            return false;
        }

        $current = $this->memberByLeg($leg);
        if (!is_array($current)) {
            return false;
        }

        $currentId = $this->legMemberIds[$leg] ?? '';
        $newId = $address . ':' . $port;
        if ($currentId === $newId && (string)($current['address'] ?? '') === $address && (int)($current['port'] ?? 0) === $port) {
            return false;
        }

        $current['address'] = $address;
        $current['port'] = $port;
        $current['leg'] = $leg;

        if ($currentId !== '' && isset($this->members[$currentId])) {
            unset($this->members[$currentId]);
        }
        $this->members[$newId] = $current;
        $this->legMemberIds[$leg] = $newId;
        foreach ($this->rtpChanMemberIds as $ssrc => $memberId) {
            if ($memberId === $currentId) {
                $this->rtpChanMemberIds[$ssrc] = $newId;
            }
        }

        return true;
    }

    private function makeSilencePayloadForMember(array $member): ?string
    {
        $codec = strtoupper($member['codec'] ?? 'PCMA');
        $frequency = (int)($member['frequency'] ?? 8000);
        $samplesPerPacket = ($member['rtpChannel'] ?? null) instanceof rtpChannel
            ? $member['rtpChannel']->samplesPerPacket
            : $this->samplesForPacket($frequency, (int)($member['ptime'] ?? $this->packetTimeMs));

        switch ($codec) {
            case 'PCMA':
                return str_repeat("\xD5", $samplesPerPacket);

            case 'PCMU':
                return str_repeat("\xFF", $samplesPerPacket);

            case 'L16':
            case 'PCM':
                $channels = $member['channels'] ?? 1;
                $samples = $samplesPerPacket * $channels;
                return str_repeat("\x00\x00", $samples);

            case 'OPUS':
                $opus = $member['opusEncoder'] ?? $member['opus'] ?? null;
                if (!$opus instanceof opusChannel) {
                    return null;
                }
                $channels = max(1, (int)($member['channels'] ?? 1));
                $pcm = str_repeat("\x00\x00", $samplesPerPacket * $channels);
                return $opus->encode($pcm);

            case 'G729':
                if (!isset($member['bcg729Channel'])) {
                    return null;
                }
                // G.729 trabalha com frames indivisíveis de 10ms (80 samples @ 8kHz).
                if ($samplesPerPacket % 80 !== 0) {
                    return null;
                }
                $pcm10ms = str_repeat("\x00\x00", 80);
                $payload = '';
                for ($frame = 0; $frame < intdiv($samplesPerPacket, 80); $frame++) {
                    $payload .= $member['bcg729Channel']->encode($pcm10ms);
                }
                return $payload;

            default:
                return null;
        }
    }

    private function makeSilencePcmForMember(string $id): string
    {
        $member = $this->members[$id] ?? null;
        $channel = $member['rtpChannel'] ?? null;
        if (!is_array($member) || !$channel instanceof rtpChannel) {
            throw new \RuntimeException('silence_member_not_found');
        }
        $format = $this->pcmFormatForMember($id);
        return str_repeat("\x00\x00", $channel->samplesPerPacket * $format['channels']);
    }

    private function samplesForPacket(int $frequency, ?int $ptimeMs = null): int
    {
        return max(1, (int)round(($frequency * ($ptimeMs ?? $this->packetTimeMs)) / 1000));
    }

    public function close(): void
    {
        $this->active = false;
        // Do not close the RTP socket from the control coroutine. The receive
        // coroutine owns recvfrom() and performs destroy() when its 200 ms
        // timeout observes active=false.

        // RtpSession installs closures that capture itself. Clear them
        // explicitly so MediaChannel -> callback -> RtpSession -> socket does
        // not retain closed kernel FDs until an eventual cyclic-GC pass.
        $this->onReceiveCallable = null;
        $this->onDtmfCallable = null;
        $this->onVadChangeCallable = null;
        $this->onRecordingCallable = null;
        $this->onStartCallable = false;
        $this->packetOnTimeoutCallable = false;

        try {
            if (!$this->eventSock->isClosed()) {
                $this->eventSock->close();
            }
        } catch (\Throwable $e) {
        }

        // Limpa o blockChannel
        if ($this->blockChannel) {
            try {
                // Consumir dados pendentes
                while (!$this->blockChannel->isEmpty()) {
                    $this->blockChannel->pop(0.001);
                }
                // Fechar o channel
                $this->blockChannel->close();
            } catch (\Throwable $e) {
            }
        }

        // Limpa os canais opus
        foreach ($this->openChannels as $channel) {
            try {
                if (is_a($channel, opusChannel::class)) {
                    if (method_exists($channel, 'destroy')) {
                        $channel->destroy();
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // Limpa os rtpChans
        foreach ($this->rtpChans as $channel) {
            try {
                if (is_a($channel, rtpChannel::class)) {
                    if (property_exists($channel, 'bcg729Channel')) {
                        $channel->bcg729Channel->close();
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // Política de fechamento: PCM residual é descartado. Completar com silêncio
        // enviaria áudio novo durante teardown e poderia atrasar/ultrapassar o BYE.
        foreach ($this->members as $id => $member) {
            try {
                $destroyedOpus = [];
                foreach (['opusEncoder', 'opusDecoder', 'opus'] as $opusKey) {
                    $opus = $member[$opusKey] ?? null;
                    if (!$opus instanceof opusChannel) {
                        continue;
                    }
                    $objectId = spl_object_id($opus);
                    if (!isset($destroyedOpus[$objectId]) && method_exists($opus, 'destroy')) {
                        $opus->destroy();
                        $destroyedOpus[$objectId] = true;
                    }
                }
                if (($member['bcg729Channel'] ?? null) instanceof bcg729Channel) {
                    $member['bcg729Channel']->close();
                }
                $this->members[$id]['pcmAccumulator'] = '';
                if (isset($member['rtpChannel'])) {
                    unset($member['rtpChannel']);
                }
            } catch (\Throwable $e) {
            }
        }

        // Limpa arrays
        $this->members = [];
        $this->injectedLegs = [];
        $this->rtpChans = [];
        $this->rtpChanMemberIds = [];
        $this->openChannels = [];

//        cli::pcl("MediaChannel fechado Call-ID: {$this->callId}", 'green');
    }


    /**
     * Encontra o PT correto do telephone-event para uma frequência específica
     * Busca primeiro na configuração registrada, depois usa fallback
     *
     * @param int $frequency Frequência do codec de áudio (8000, 16000, 48000, etc)
     * @return int PT do telephone-event (geralmente 101)
     */
    private function findTelephoneEventPt(int $frequency): int
    {
        // Buscar nos codecs registrados com a frequência exata
        $targetKey = 'telephone-event_' . $frequency;
        if (isset($this->ptCodecsFrequency[$targetKey])) {
            // Encontrar o PT correspondente
            foreach ($this->ptCodecs as $pt => $codecName) {
                if (
                    strtolower($codecName) === 'telephone-event'
                    && (int)($this->ptFrequencies[$pt] ?? 0) === $frequency
                ) {
                    return $pt;
                }
            }
        }

        // Fallback: buscar telephone-event sem verificar frequência
        foreach ($this->ptCodecs as $pt => $codecName) {
            if (strtolower($codecName) === 'telephone-event') {
                return $pt;
            }
        }

        // Fallback final: PT 101 (padrão RFC 4733)
        return 101;
    }

    public array $ptCodecsChannels = [];

    public function registerPtCodecs(array $ptCodecs): void
    {
        $preRender = [
            0 => 'PCMU/8000',
            8 => 'PCMA/8000',
            18 => 'G729/8000',
            101 => 'telephone-event/8000',
        ];

        // Registrar codecs padrão primeiro
        foreach ($preRender as $pt => $codec) {
            $parts = explode('/', $codec);
            $codecName = $parts[0] ?? $pt;
            $frequency = (int)($parts[1] ?? 8000);

            $this->ptCodecs[$pt] = $codecName;
            $this->ptFrequencies[$pt] = $frequency;

            // Para telephone-event, usar chave composta para suportar múltiplas frequências
            if (strtolower($codecName) === 'telephone-event') {
                $this->ptCodecsFrequency[$codecName . '_' . $frequency] = $frequency;
            } else {
                $this->ptCodecsFrequency[$codecName] = $frequency;
            }
        }

        // Sobrescrever com codecs fornecidos pelo SDP
        foreach ($ptCodecs as $pt => $codec) {
            $parts = explode('/', $codec);
            $codecName = $parts[0] ?? $pt;
            $frequency = (int)($parts[1] ?? 8000);
            $channels = (int)($parts[2] ?? 1);
            $this->ptCodecsChannels[$pt] = $channels;

            $this->ptCodecs[$pt] = $codecName;
            $this->ptFrequencies[$pt] = $frequency;

            // Para telephone-event, usar chave composta para suportar múltiplas frequências
            if (strtolower($codecName) === 'telephone-event') {
                $this->ptCodecsFrequency[$codecName . '_' . $frequency] = $frequency;
            } else {
                $this->ptCodecsFrequency[$codecName] = $frequency;
            }
        }
    }

    public function packetOnTimeout(callable $param): void
    {
        $this->packetOnTimeoutCallable = $param;
    }

    public mixed $packetOnTimeoutCallable = false;

    public function getFrequencyFromPtCodec(int $pt)
    {
        if (isset($this->ptCodecsFrequency[$this->ptCodecs[$pt]])) {
            return $this->ptCodecsFrequency[$this->ptCodecs[$pt]];
        }
        return 8000;
    }


    /**
     * Processa eventos telephone-event (RFC 4733/2833)
     * Implementa tratamento correto de DTMF com:
     * - Detecção de retransmissões (RFC requer 3 pacotes finais)
     * - Validação de flag E (End)
     * - Debouncing baseado em timestamp + sequence
     * - Forward correto para outros membros
     */
    private function processDtmf(rtpc $rtpc, mixed $peer, Closure $closure): void
    {
        Coroutine::create(function () use ($rtpc, $peer, $closure) {
            $ssrc = $rtpc->ssrc;
            $payload = $rtpc->payloadRaw;
            $sequence = $rtpc->sequence;
            $timestamp = $rtpc->timestamp;

            // RFC 4733: payload mínimo = 4 bytes
            if (strlen($payload) < 4) {
                return;
            }

            // RFC 4733 Section 2.3: Event Payload Format
            // 0                   1                   2                   3
            // 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
            // +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
            // |     event     |E|R| volume    |          duration             |
            // +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
            $data = unpack('Cevent/Cflags/nduration', $payload);
            $event = $data['event'];
            $flags = $data['flags'];
            $isEnd = ($flags & 0x80) !== 0; // Flag E (End of Event)
            $volume = $flags & 0x3F; // 6 bits de volume
            $duration = $data['duration'];


            // Criar chave única para este evento específico
            $cacheKey = "{$ssrc}:{$timestamp}:{$event}";

            // RFC 4733 Section 2.5.1.4: Detectar retransmissões
            // O timestamp permanece o mesmo durante todo o evento
            // Sequence number incrementa a cada pacote
            if (isset($this->dtmfPacketCache[$cacheKey])) {
                $cached = $this->dtmfPacketCache[$cacheKey];

                // Se já processamos um pacote END para este evento, ignorar retransmissões
                if ($cached['processed'] && $isEnd) {
                    // RFC: últimos 3 pacotes são idênticos com flag E set
                    $closure(); // Fazer forward do pacote mesmo sendo duplicado
                    // Disparar callback de DTMF
                    $callback = $this->onDtmfCallable;
                    if (is_callable($callback)) {
                        if (($rtpc->sequence - $cached['sequence']) == 1) {
                            go($callback, $event, $peer, $event, $this);
                        }


                    }
                    return;
                }

                // Atualizar informações do evento em andamento
                $this->dtmfPacketCache[$cacheKey]['sequence'] = $sequence;
                $this->dtmfPacketCache[$cacheKey]['duration'] = $duration;
                $this->dtmfPacketCache[$cacheKey]['lastSeen'] = microtime(true);
            } else {
                // Novo evento DTMF iniciado
                $this->dtmfPacketCache[$cacheKey] = [
                    'event' => $event,
                    'timestamp' => $timestamp,
                    'sequence' => $sequence,
                    'duration' => $duration,
                    'volume' => $volume,
                    'firstSeen' => microtime(true),
                    'lastSeen' => microtime(true),
                    'processed' => false,
                ];
            }


            // Forward para outros membros (sempre, para manter sincronização)
            $closure();

            // Processar apenas quando flag E (End) está setada
            if (!$isEnd) {

                return;
            }

            // Verificar se já processamos este evento
            if ($this->dtmfPacketCache[$cacheKey]['processed']) {

                return;
            }

            // Validação de debounce entre eventos diferentes
            // RFC 4733: eventos diferentes devem ter timestamps diferentes
            if (isset($this->dtmfLastEvent[$ssrc])) {
                $last = $this->dtmfLastEvent[$ssrc];

                // Se for o mesmo evento com timestamp muito próximo, ignorar
                if ($last['event'] === $event && $timestamp === $last['timestamp']) {
                    return;
                }

                // Debounce adicional: mínimo 50ms entre eventos (400 samples @ 8kHz)
                $timeDiffSamples = abs($timestamp - $last['timestamp']);
                $timeDiffMs = ($timeDiffSamples / 8); // 8000Hz = 8 samples/ms

                if ($timeDiffMs < 50 && $last['event'] === $event) {
                    return;
                }
            }

            // Marcar como processado
            $this->dtmfPacketCache[$cacheKey]['processed'] = true;

            // Atualizar último evento processado
            $this->dtmfLastEvent[$ssrc] = [
                'event' => $event,
                'timestamp' => $timestamp,
                'sequence' => $sequence,
                'duration' => $duration,
                'time' => microtime(true),
            ];

            // Traduzir evento para dígito
            $digit = $this->translateDigit($event);


            // Guard temporal contra disparo múltiplo do mesmo dígito.
            // O RFC 4733 retransmite o pacote final (flag E) 3x; se essas retransmissões
            // chegarem com timestamp/SSRC variando, o cacheKey muda e o dedup acima não
            // as captura, causando o callback disparar 3x para uma única tecla pressionada.
            // Aqui garantimos um único disparo por (ssrc, event) dentro da janela de debounce.
            $nowMs = microtime(true) * 1000;
            $lastFired = $this->dtmfFiredGuard[$ssrc] ?? null;
            if (
                $lastFired !== null &&
                $lastFired['event'] === $event &&
                ($nowMs - $lastFired['time']) < $this->dtmfDebounceMs
            ) {
                return;
            }
            $this->dtmfFiredGuard[$ssrc] = ['event' => $event, 'time' => $nowMs];



            // Limpar cache antigo (> 5 segundos)
            $currentTime = microtime(true);
            foreach ($this->dtmfPacketCache as $key => $cache) {
                if ($currentTime - $cache['lastSeen'] > 5.0) {
                    unset($this->dtmfPacketCache[$key]);
                }
            }
        });
    }

    private function translateDigit(mixed $event): string
    {
        $mapa = [
            0 => '0',
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
            10 => '*',
            11 => '#',
        ];
        return $mapa[$event] ?? '';
    }
}
