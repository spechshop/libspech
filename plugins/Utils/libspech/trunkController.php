<?php

namespace libspech\Sip;

use Closure;
use co;
use libspech\Cache\rpcClient;
use libspech\Cli\cli;
use libspech\Network\network;
use libspech\Packet\renderMessages;
use libspech\Rtp\MediaChannel;
use libspech\Rtp\PcmStreamConverter;
use libspech\Rtp\rtpc;
use libspech\Rtp\rtpChannel;
use Random\RandomException;
use SocketMutable;
use Swoole\Coroutine;
use Swoole\Coroutine\Socket;
use Swoole\StringObject;
use Swoole\Timer;

class trunkController
{
    private const OPUS_PACKET_TIMES_MS = [5, 10, 20, 40, 60, 80, 100, 120];
    private const RTP_AVP_STATIC_CODECS = [
        0 => 'PCMU/8000/1',
        3 => 'GSM/8000/1',
        8 => 'PCMA/8000/1',
        10 => 'L16/44100/2',
        11 => 'L16/44100/1',
        18 => 'G729/8000/1',
    ];

    public bool $callableRingInvoked = false;
    public mixed $username;
    public mixed $password;
    public mixed $host;
    public mixed $port;
    public SocketMutable $socket;
    public int $expires;
    public string $sipLocalIp;
    public string $localIp;
    public string $callId;
    public int $timestamp = 0;
    public int $audioReceivePort;
    public string $nonce = "";
    public bool $isRegistered = false;
    public int $csq;
    public int $ssrc;
    public bool $error = false;
    public bool $callActive = false;
    public int $timeoutCall;
    public string $callerId;
    public int $registerCount = 0;
    public int $socketPortListen = 0;
    public int $connectTimeout = 30;
    public array $headersNeedAuthorization = [
        "Proxy-Authenticate" => "Proxy-Authorization",
        "WWW-Authenticate" => "Authorization",
    ];
    public array $progressCodes = [
        180,
        181,
        182,
        183,
    ];
    public array $successCodes = [
        200,
        202,
        204,
    ];
    public array $failureCodes = [
        'CANCEL',
        'BYE',
        400,
        403,
        484,
        404,
        503,
        405,
        406,
        408,
        488,
        410,
        500,
        501,
        502,
        504,
        505,
        513,
        580,
        600,
        603,
        604,
        606,
    ];

    public string $bufferAudio = "";
    public mixed $sequenceNumber = 0;
    public array $dtmfList = [];
    public int $lastTime = 0;
    public bool $receiveBye = false;
    public bool $preserveSockets = false;

    public $headers200;
    public string $calledNumber;
    public int $audioRemotePort = 0;
    public string $audioRemoteIp = "";
    public array $volumesAverage = [];
    public array $originVolumes = [];
    public bool $allowBuffer = false;
    public array $byeRecovery = [];
    public $onFailedCallback;
    public $onAnswerCallback;
    public ?string $fileRecord = null;
    public bool $speakWait = false;
    public int $speakWaitTime = 0;
    public array $speakWaitSequence = [];
    public int|float $lastSpeakTime = 0;
    public bool $blockSpeak = false;
    public int|float $speakTimeStart = 0;
    public bool $startSpeak = false;
    public int $totalSequence = 3;
    public array $socketsList = [];
    public bool $inTransfer = false;
    public $currentTtsBlock;

    public mixed $currentMethod = null;
    public mixed $globalInfo = [];
    public array $dtmfCallbacks = [];
    public array $supportedCodecs = [
        0 => ["rtpmap:0 PCMU/8000"],
        18 => [
            "rtpmap:18 G729/8000",
            "fmtp:18 annexb=no",
        ],
        101 => [
            "rtpmap:101 telephone-event/8000",
            "fmtp:101 0-16",
        ],
        8 => ["rtpmap:8 PCMA/8000"],
        3 => ["rtpmap:3 GSM/8000"],
    ];
    public array $members = [];
    public bool|string $domain = false;
    public array $ssrcSequences = [];
    public string $currentState = "";
    public string $codecMediaLine = "";
    public array $codecRtpMap = [];
    public array $box = [];
    public array $bufferWriteSound = [];
    public array $volumeCodec = [];
    public $rtpSocket;
    public $remoteIp;
    public $remotePort;
    public bool $newSound = false;
    public string $audioFilePath = '';
    public int $currentCodec = 8;
    public \bcg729Channel $channel;

    public array $listeners = [];
    public bool $enableAudioRecording = false;
    public string $recordAudioBuffer = '';
    public array $dtmfClicks = [];
    public $onHangupCallback;
    public int $closeCallInTime = 0;
    public $onRingingCallback;
    public $socketInUse;
    public $waitingEnd = 0;
    private $audioFileHandle;
    private ?\libspech\Audio\AudioFilePlayback $audioPlayback = null;
    public int $speakStartThreshold = 2;
    public int $speakEndThreshold = 3;
    public $prefix = '';
    public array $ptsRegistered = [];
    public array $ptsDtmfRegistered = [];
    public array $mapLearn = [];
    public $codecName;
    public int $frequencyCall = 8000;
    public \Closure $onBuildAudio;
    public rtpChannel $rtpChannel;
    public array $inviteHeaders = [];
    private bool $proxyMediaActive = false;
    private ?string $currentProxyId = null;
    public string $userAgent = 'SPECHSHOP LIB';
    private string|int|null $ptTelephoneEvent;
    private string|int|null $ptUse=8;
    private string|int|null $txPt=8;
    private string|int|null $rxPt=8;
    /** @var array<int,string> Explicit mappings from the remote SDP used for TX. */
    public array $txCodecMapper = [];
    /** @var array<int,string> Explicit mappings from libspech's local SDP used for RX. */
    public array $rxCodecMapper = [];
    public array $sdp;
    public $bcgChannel;
    public bool $closing = false;
    protected bool $socketReadInProgress = false;
    private int $cid;
    private array $idTimers = [];
    public ?array $lastPacket = [];
    public mixed $sdpReceived = [];

    public bool $cancelSent = false;
    public bool $cancelOkReceived = false;
    public bool $inviteAcceptedAfterCancel = false;
    public bool $byeSent = false;
    public bool $answerCallbackInvoked = false;
    private int $packetTime = MediaChannel::DEFAULT_PACKET_TIME_MS;
    private int $configuredPacketTime = MediaChannel::DEFAULT_PACKET_TIME_MS;
    private ?int $remoteMaxPacketTime = null;
    private int $sipIpVersion = 4;
    private string $sipHostSource = '';


    private function safeRecvfrom(&$peer, $timeout = 1)
    {
        if ($this->socketReadInProgress) {
            return null;
        }
        $this->socketReadInProgress = true;
        try {
            return $this->socket->recvfrom($peer, $timeout);
        } catch (\Throwable $e) {
            return false;
        } finally {
            $this->socketReadInProgress = false;
        }
    }

    /**
     * @throws RandomException
     */
    public function __construct(
        mixed $username,
        mixed $password,
        mixed $host,
        mixed $port = 5060,
        mixed $domain = false,
        mixed $sipIpVersion = 4
    )
    {
        $this->onBuildAudio = fn($data) => $data;
        $this->bcgChannel = new \bcg729Channel();
        $this->username = $username ?? "";
        $this->callerId = $username ?? "";
        $this->password = $password ?? "";
        $this->domain = $domain ?? false;
        $this->socketInUse = false;
        $this->onHangupCallback = null;
        $this->onFailedCallback = null;
        $this->onAnswerCallback = null;
        $this->onRingingCallback = null;
        $this->audioFileHandle = null;
        $this->cid = Coroutine::getCid();
        $this->onDtmfCallable = fn($digit) => $digit;

        $this->sipIpVersion = $this->validateSipIpVersion((int)$sipIpVersion);
        $this->sipHostSource = network::extractHost($host);
        $this->host = network::resolveAddress($this->sipHostSource, $this->sipIpVersion);
        $this->port = $port;
        $this->expires = 300;
        $this->timeoutCall = time();


        $this->calledNumber = "";

        $this->csq = rand(100, 99999);


        $this->ssrc = random_int(0, 0xffffffff);
        $this->callId = bin2hex(secure_random_bytes(8));


        $this->rtpSocket = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);

        do {
            $port = network::getFreePort('udp');

            // RTP deve ser par. Se cair ímpar, volta uma porta.
            if ($port % 2 !== 0) {
                $port--;
            }

            $rtpPort = $port;


            // Garante que o par RTP/RTCP está disponível
            $rtpAvailable = network::isPortAvailable($rtpPort, 'udp');


        } while (!$rtpAvailable);

        $this->audioReceivePort = $rtpPort;


        if (!$this->rtpSocket->bind('0.0.0.0', $this->audioReceivePort)) {
            throw new \RuntimeException(
                "Erro ao bindar RTP em {$this->audioReceivePort}: {$this->rtpSocket->errCode} {$this->rtpSocket->errMsg}"
            );
        }


//        cli::pcl("Audio Receive Port: {$this->audioReceivePort}");
        // A mídia permanece explicitamente IPv4; sipLocalIp é exclusivo da sinalização.
        $this->localIp = network::getLocalIp(4);
        $this->sipLocalIp = network::getLocalIp($this->sipIpVersion);


        [$this->socket, $this->socketPortListen] = $this->createSipSocket($this->sipIpVersion);


        $this->socketsList[] = $this->socket;
        $this->socketsList[] = $this->rtpSocket;
        $this->onDtmfCallable = fn($digit) => $digit;


        $this->lastTime = time();
        $this->receiveBye = false;
        $this->error = false;
        $this->callActive = false;
        $this->members = [];


        // send options


        $this->userAgent = 'SPECHSHOP LIB';
        $options = $this->renderSip($this->modelOptions());

        $this->sendSipTo($this->host, $this->port, $options);
        $res = $this->safeRecvfrom($peer, 1);


        /** @var ? $peer */

        $this->mediaChannel = new MediaChannel($this->rtpSocket, $this->callId);
        $this->setPacketTime($this->packetTime);


    }

    public function getSipIpVersion(): int
    {
        return $this->sipIpVersion;
    }

    public function getSipLocalIp(): string
    {
        return $this->sipLocalIp;
    }

    /**
     * Troca somente o transporte SIP. Deve ser chamado antes de REGISTER/INVITE.
     */
    public function setSipIpVersion(int $sipIpVersion): self
    {
        $sipIpVersion = $this->validateSipIpVersion($sipIpVersion);
        if ($sipIpVersion === $this->sipIpVersion) {
            return $this;
        }
        if ($this->closing || $this->isRegistered || $this->callActive || $this->socketReadInProgress) {
            throw new \LogicException('A versão IP do SIP só pode ser alterada antes de REGISTER/INVITE');
        }

        $resolvedHost = network::resolveAddress($this->sipHostSource, $sipIpVersion);
        $sipLocalIp = network::getLocalIp($sipIpVersion);
        [$socket, $socketPort] = $this->createSipSocket($sipIpVersion);

        $oldSocket = $this->socket;
        $this->socket = $socket;
        $this->socketPortListen = $socketPort;
        $this->sipIpVersion = $sipIpVersion;
        $this->sipLocalIp = $sipLocalIp;
        $this->host = $resolvedHost;
        $this->replaceSipSocketInList($oldSocket, $socket);

        if (!$oldSocket->isClosed()) {
            $oldSocket->close();
        }

        $options = $this->renderSip($this->modelOptions());
        $this->sendSipTo($this->host, $this->port, $options);
        $this->safeRecvfrom($peer, 1);

        return $this;
    }

    private function validateSipIpVersion(int $sipIpVersion): int
    {
        network::socketFamily($sipIpVersion);
        return $sipIpVersion;
    }

    /** @return array{0: SocketMutable, 1: int} */
    private function createSipSocket(int $sipIpVersion, ?int $port = null): array
    {
        $family = network::socketFamily($sipIpVersion);
        $bindAddress = $sipIpVersion === 4 ? '0.0.0.0' : '::';
        $port ??= network::getFreePort('udp', $sipIpVersion);
        if ($port === null) {
            throw new \RuntimeException("Não foi possível obter uma porta UDP para SIP IPv{$sipIpVersion}");
        }

        $socket = new SocketMutable($family, SOCK_DGRAM, SOL_UDP);
        if ($sipIpVersion === 6 && !$socket->setOption(IPPROTO_IPV6, IPV6_V6ONLY, 0)) {
            throw new \RuntimeException(
                "Não foi possível habilitar o modo dual-stack no socket SIP IPv6: {$socket->errCode} {$socket->errMsg}"
            );
        }
        if (!$socket->bind($bindAddress, $port)) {
            throw new \RuntimeException(
                "Erro ao bindar SIP IPv{$sipIpVersion} em {$bindAddress}:{$port}: {$socket->errCode} {$socket->errMsg}"
            );
        }
        return [$socket, $port];
    }

    private function reopenSipSocket(): bool
    {
        $oldSocket = $this->socket;
        if (!$oldSocket->isClosed()) {
            $oldSocket->close();
        }
        try {
            [$socket] = $this->createSipSocket($this->sipIpVersion, $this->socketPortListen);
        } catch (\Throwable $exception) {
            cli::pcl($exception->getMessage(), 'red');
            return false;
        }

        $this->socket = $socket;
        $this->replaceSipSocketInList($oldSocket, $socket);
        return true;
    }

    private function replaceSipSocketInList(SocketMutable $oldSocket, SocketMutable $newSocket): void
    {
        foreach ($this->socketsList as $index => $listedSocket) {
            if ($listedSocket === $oldSocket) {
                $this->socketsList[$index] = $newSocket;
                return;
            }
        }
        $this->socketsList[] = $newSocket;
    }

    private function renderSip(array $model): string
    {
        return sip::renderSolution($model, $this->sipLocalIp);
    }

    private function sendSipTo(mixed $host, mixed $port, string $packet): int|false
    {
        $address = network::resolveAddressForSocket($host, $this->sipIpVersion);
        return $this->socket->sendto($address, (int)$port, $packet);
    }

    private function sipViaAddress(?int $port = null): string
    {
        return sip::formatHostPort($this->sipLocalIp, $port ?? $this->socketPortListen);
    }

    private function sipServerUri(string $user = '', bool $includeDefaultPort = false): string
    {
        return sip::renderSipUri($user, (string)$this->host, $this->port, $includeDefaultPort);
    }

    /**
     * Construir array OPTIONS para keepalive/ping do servidor SIP
     *
     * OPTIONS é usado para verificar conectividade e capacidades do servidor.
     * Geralmente enviado no constructor para testar conexão básica.
     *
     * Estrutura:
     * [
     *     "method" => "OPTIONS",
     *     "methodForParser" => "OPTIONS sip:host SIP/2.0",
     *     "headers" => [
     *         "Via" => [...],
     *         "From" => ["<sip:username@host>;tag=..."],
     *         "To" => ["<sip:host>"],
     *         "Call-ID" => ["..."],
     *         "CSeq" => ["N OPTIONS"],
     *         "Contact" => ["<sip:username@ip:port>"],
     *         "User-Agent" => ["SPECHSHOP LIB"],
     *         "Expires" => ["120"],
     *         ...
     *     ]
     * ]
     *
     * @return array Array de sinalização OPTIONS
     *
     * @see SIGNALING_ARRAYS.md para documentação completa
     */
    public function modelOptions(): array
    {
        $modelRegister = $this->modelRegister()['headers'];
        return renderMessages::generateModelOptions($modelRegister, $this->socketPortListen, $this->sipLocalIp);

    }

    public static function extractVia(string $line): array
    {
        return sip::extractVia($line);
    }

    public int $defaultChannels = 1;

    public function enableStereoSound(): void
    {


        $ptFind=false;
        foreach ($this->mapLearn as $pt => $aValues) {
            foreach ($aValues as $a) {
                if (str_contains($a, 'opus')) {
                    $ptFind=$pt;
                    break 2;
                }
            }
        }
        if (!$ptFind) return;
        $dtmf48k=$this->ptsDtmfRegistered[48000];
        unset($this->mapLearn[$ptFind]);
        unset($this->mapLearn[$dtmf48k]);
        unset($this->ptsRegistered[$ptFind]);
        unset($this->ptsRegistered[$dtmf48k]);
        $this->stereoMode=true;

        $this->mountLineCodecSDP('OPUS/48000/2');
        $this->defaultChannels=2;
    }
    public bool $stereoMode=false;

    public function mountLineCodecSDP(string $codec = 'PCMA/8000'): array
    {
        $defaultRate = 8000;
        $defaultChannels = 1;

        $parts = explode('/', trim($codec));
        $rawName = trim($parts[0] ?? 'PCMA');
        $nameUpper = strtoupper($rawName);
        $name = $nameUpper;

        if ($nameUpper === 'OPUS') {
            $name = 'opus';
        }
        if ($nameUpper === 'TELEPHONE-EVENT') {
            $name = 'telephone-event';
        }

        $this->validatePacketTimeForCodec($this->configuredPacketTime, $nameUpper);

        if (!empty($parts[1])) {
            $defaultRate = (int)$parts[1];
        }
        if (!empty($parts[2])) {
            $defaultChannels = (int)$parts[2];
        }

        $pt = null;
        $mainLines = [];

        $ptStrict = [
            'PCMU' => 0,
            'GSM' => 3,
            'PCMA' => 8,
            'G729' => 18,
            'TELEPHONE-EVENT' => 101,
        ];

        if (array_key_exists($nameUpper, $ptStrict)) {
            $pt = $ptStrict[$nameUpper];
        }

        if ($pt === null) {
            for ($i = 97; $i < 128; $i++) {
                if (!array_key_exists($i, $this->ptsRegistered) && !array_key_exists($i, $this->mapLearn)) {
                    $pt = $i;
                    break;
                }
            }
        }

        if ($pt === null) {
            throw new \RuntimeException('Não foi possível alocar payload type dinâmico para SDP');
        }

        $lineString = "rtpmap:$pt $name/$defaultRate";
        if ($defaultChannels > 1) {
            $lineString .= "/$defaultChannels";
        }
        $mainLines[] = $lineString;

        if ($name === 'opus') {
            $v = "fmtp:$pt maxplaybackrate=24000;sprop-maxcapturerate=24000;maxaveragebitrate=64000;useinbandfec=1";
            if ($this->stereoMode) {
                $v .= ";stereo=1";
            }
            $mainLines[] = $v;
        }

        if ($pt === 18) {
            $mainLines[] = "fmtp:$pt annexb=no";
        }

        $this->ptsRegistered[$pt] = $lineString;

        if (array_key_exists($defaultRate, $this->ptsDtmfRegistered)) {
            $ptDtmf = (int)$this->ptsDtmfRegistered[$defaultRate];
        } else {
            $ptDtmf = 101;
            for ($i = 101; $i < 128; $i++) {
                if (!array_key_exists($i, $this->mapLearn) && !array_key_exists($i, $this->ptsRegistered)) {
                    $ptDtmf = $i;
                    break;
                }
            }
            $this->ptsDtmfRegistered[$defaultRate] = $ptDtmf;
        }

        $dtmfLines = [
            "rtpmap:$ptDtmf telephone-event/$defaultRate",
            "fmtp:$ptDtmf 0-16",
        ];

        $this->mapLearn[$pt] = $mainLines;
        $this->mapLearn[$ptDtmf] = $dtmfLines;
        $this->defaultChannels = $defaultChannels;

        return [
            $pt => $mainLines,
            $ptDtmf => $dtmfLines,
        ];
    }

    public function __invoke(): void
    {
        $callId = $this->callId;
        cli::pcl("CALL ID {$callId} foi criado");
    }

    public function setupForIncoming(int $ptUse, string $codecName, int $frequencyCall, array $sdpReceived = []): void
    {
        $this->validatePacketTimeForCodec($this->configuredPacketTime, $codecName);
        $this->txPt = $ptUse;
        $this->rxPt = $ptUse;
        $this->ptUse = $ptUse;
        $this->codecName = $codecName;
        $this->frequencyCall = $frequencyCall;
        $this->rxCodecMapper = [];
        $this->storeRemoteSdp($sdpReceived);
        $remoteMapper = self::codecMapperFromSdp($sdpReceived);
        $mapping = strtoupper($codecName) . '/' . $frequencyCall . '/1';
        $this->rxCodecMapper = $remoteMapper !== [] ? $remoteMapper : [$ptUse => $mapping];
        $this->txCodecMapper = [$ptUse => $mapping];
        $this->mapLearn[$ptUse] = ["rtpmap:{$ptUse} {$codecName}/{$frequencyCall}"];
    }

    public function removeMember(string $username): void
    {
        if (in_array($username, $this->members)) {
            $key = array_search($username, $this->members);
            unset($this->members[$key]);
        }
    }

    public function isMember(string $username): bool
    {
        return in_array($username, $this->members);
    }

    public function saveGlobalInfo(string $key, $value): void
    {
        $this->globalInfo[$key] = $value;
    }

    public function record(string $file): void
    {
        $this->allowBuffer = true;
        $this->fileRecord = $file;
    }

    public function onFailed(Closure $callback): void
    {
        $this->onFailedCallback = $callback;
    }

    public function onAnswer(callable $callback): void
    {
        $this->onAnswerCallback = $callback;
    }

    public function onRinging(Closure $param): void
    {
        $this->onRingingCallback = $param;
    }

    public function volumeAverage(string $pcm, int $sampleRate = 8000, int $channels = 1): float
    {
        if (empty($pcm)) {
            return 0.0;
        }

        $sampleRate = max(1, $sampleRate);
        $channels = max(1, $channels);
        $analysisTimeMs = min(10, $this->packetTime);
        $expectedSamples = max(1, (int)round($sampleRate * ($analysisTimeMs / 1000) * $channels));
        $numSamples = min($expectedSamples, intdiv(strlen($pcm), 2));
        if ($numSamples <= 0) {
            return 0.0;
        }

        $pcm = substr($pcm, 0, $numSamples * 2);
        $soma = 0;
        $maxValue = 32768.0;
        for ($i = 0, $length = strlen($pcm); $i < $length; $i += 2) {
            $sample = unpack("s", substr($pcm, $i, 2))[1];
            $soma += $sample * $sample;
        }
        $rms = sqrt($soma / $numSamples);
        $normalized = $rms / $maxValue;
        return max(1, min(100, round($normalized * 100, 2)));
    }


    public function send2833(mixed $digit): void
    {
        if ($this->mediaChannel instanceof MediaChannel) {
            $this->mediaChannel->send2833($digit);
        }
    }


    public mixed $route = false;

    private function getCSeqMethod(array $message): string
    {
        $cseqHeader = $message['headers']['CSeq'][0] ?? '';
        return sip::letters($cseqHeader);
    }

    public function buildOkForRequest(array $request): string
    {
        return renderMessages::respond200OK($request['headers'], '', $this->sipLocalIp);
    }

    public function sendOkForRequest(array $request, array $peer): bool
    {
        return (bool)$this->sendSipTo($peer['address'], $peer['port'], $this->buildOkForRequest($request));
    }

    /**
     * Aguarda respostas ao INVITE sem enviar novo INVITE
     *
     * Usado quando o INVITE já foi enviado por outro socket (ex: 5060)
     * e apenas aguarda as respostas roteadas para este trunkController
     *
     * @param string $to Número chamado
     * @param int $maxRings Timeout em segundos
     * @return bool
     */
    public function waitRoutedDialog(string $to, $maxRings = 120): bool
    {
        $timeRing = time();

        for (; ;) {
            if ($this->closing || $this->socket->isClosed()) {
                if (!$this->callActive && !$this->answerCallbackInvoked && is_callable($this->onFailedCallback)) {
                    go($this->onFailedCallback, "Socket fechado antes de resposta");
                }
                return false;
            }

            if (time() - $timeRing > $maxRings) {
                $this->error = true;
                if (!$this->callActive && is_callable($this->onFailedCallback)) {
                    go($this->onFailedCallback, "Timeout após {$maxRings}s sem resposta");
                }
                return false;
            }

            if ($this->error) {
                return false;
            }

            /** @var ?array $peer */
            $packet = $this->safeRecvfrom($peer, 10);

            // null = socket em uso por outra corrotina (ex.: unRegister concorrente) — pula iteração
            if ($packet === null || $packet === false || $packet === "") {
                continue;
            }


            $receive = sip::parse($packet);
            if (empty($receive['method'])) {
                continue;
            }

            // Normalize compact Call-ID header
            if (!array_key_exists("Call-ID", $receive["headers"])) {
                if (array_key_exists("i", $receive["headers"])) {
                    $receive["headers"]["Call-ID"] = [$receive["headers"]["i"][0]];
                } else {
                    continue;
                }
            }

            // Filter by Call-ID
            $msgCallId = $receive["headers"]["Call-ID"][0];
            if ($msgCallId !== $this->callId) {
                $callerCallId = $this->globalInfo['callerCallId'] ?? null;
                if ($callerCallId === null || $msgCallId !== $callerCallId) {
                    continue;
                }
            }

            $this->currentMethod = $receive["method"];
            $this->lastPacket = $receive;

            if (array_key_exists('Record-Route', $receive["headers"])) {
                $this->route = $receive["headers"]["Record-Route"][0];
            }

            $method = $receive["method"];

            // ── Incoming requests ─────────────────────────────────────────────

            if ($method === "OPTIONS") {
                $this->sendOkForRequest($receive, $peer);
                continue;
            }

            if ($method === "CANCEL") {
                $this->sendOkForRequest($receive, $peer);
                $this->cancelOkReceived = true;
                if (!$this->callActive) {
                    $this->error = true;
                    if (is_callable($this->onFailedCallback)) {
                        go($this->onFailedCallback, "CANCEL recebido da outra parte");
                    }
                    return false;
                }
                continue;
            }

            if ($method === "BYE") {
                $this->receiveBye = true;
                $this->saveGlobalInfo('lastPeer', $peer);
                $this->sendOkForRequest($receive, $peer);
                if (is_callable($this->onHangupCallback)) {
                    go($this->onHangupCallback, $this);
                }
                return false;
            }

            // ── Numeric response codes ────────────────────────────────────────

            // 1xx Progress
            if (in_array($method, $this->progressCodes)) {
                if (!$this->callableRingInvoked && is_callable($this->onRingingCallback)) {
                    go($this->onRingingCallback, $this);
                    $this->onRingingCallback = null;
                    $this->callableRingInvoked = true;
                }
                if (array_key_exists('sdp', $receive)) {
                    $this->audioRemoteIp = explode(" ", $receive["sdp"]["c"][0])[2];
                    $this->audioRemotePort = (int)explode(" ", $receive["sdp"]["m"][0])[1];
                    $this->storeRemoteSdp($receive['sdp']);
                }
                continue;
            }

            // 487 Request Terminated — send ACK, fire onFailed
            if ($method === '487') {
                $ackModel = $this->ackModel($receive["headers"]);
                if (!empty($ackModel)) {
                    $this->sendSipTo($this->host, $this->port, $this->renderSip($ackModel));
                }
                $this->error = true;
                if (is_callable($this->onFailedCallback)) {
                    go($this->onFailedCallback, "487 Request Terminated");
                }
                return false;
            }

            // Other 4xx/5xx/6xx failure codes
            $numericMethod = (int)$method;
            if ($numericMethod >= 400 && $numericMethod < 700) {
                $this->error = true;
                if (is_callable($this->onFailedCallback)) {
                    go($this->onFailedCallback, $receive['methodForParser'] ?? $method);
                }
                return false;
            }

            // 2xx Success — classify by CSeq method
            if (in_array($method, $this->successCodes)) {
                $cseqMethod = $this->getCSeqMethod($receive);

                if ($cseqMethod === 'CANCEL') {
                    $this->cancelOkReceived = true;
                    continue;
                }

                if ($cseqMethod === 'BYE') {
                    return true;
                }

                if ($cseqMethod === 'OPTIONS') {
                    continue;
                }

                if ($cseqMethod === 'INVITE') {
                    // Always send ACK for 200 OK INVITE
                    $ackModel = $this->ackModel($receive["headers"]);
                    if (!empty($ackModel)) {
                        $ifr = sip::extractURI($receive['headers']['Contact'][0])['peer'];
                        $this->sendSipTo($this->host, $this->port, $this->renderSip($ackModel));
                        $this->sendSipTo($ifr['host'], (int)$ifr['port'], $this->renderSip($ackModel));
                    }

                    // Update audio destination from SDP
                    if (array_key_exists('sdp', $receive)) {
                        $this->audioRemoteIp = explode(" ", $receive["sdp"]["c"][0])[2];
                        $this->audioRemotePort = (int)explode(" ", $receive["sdp"]["m"][0])[1];
                        $this->storeRemoteSdp($receive['sdp']);
                    }

                    $this->headers200 = $receive;

                    // CANCEL/200 OK crossing: we sent CANCEL but answer arrived first
                    if ($this->cancelSent) {
                        $this->inviteAcceptedAfterCancel = true;
                        Coroutine::sleep(0.05);
                        try {
                            $this->bye();
                        } catch (\Throwable) {
                        }
                        return false;
                    }

                    if (!array_key_exists('sdp', $receive)) {
                        // 200 OK without SDP — wait for re-INVITE
                        continue;
                    }

                    // Confirm dialog
                    $this->callActive = true;

                    if (!$this->answerCallbackInvoked && is_callable($this->onAnswerCallback)) {
                        $this->answerCallbackInvoked = true;
                        go($this->onAnswerCallback, $this);
                    }

                    $timeRing = time();
                    continue;
                }

                // Unknown CSeq method in 2xx — ignore
                continue;
            }
        }
    }

    public function call(string $to, $maxRings = 120): bool
    {
        $authSent = false;
        $firstPacketReceived = false;

        $modelInvite = $this->modelInvite($to, $this->prefix);

        /*
         * Guarda o To original do INVITE.
         * Ele precisa continuar SEM tag no INVITE autenticado.
         */
        $originalToHeader = $modelInvite['headers']['To'] ?? [];

        /*
         * ACK de resposta final negativa/challenge de INVITE, ex: 401/407.
         * Esse ACK usa a MESMA transação do INVITE rejeitado:
         * - mesmo Via/branch do INVITE enviado
         * - mesmo CSeq numérico
         * - To com tag recebido na resposta
         */
        $sendInviteChallengeAck = function (array $responseHeaders, array $lastInviteModel): void {
            if (
                empty($responseHeaders['CSeq'][0]) ||
                empty($responseHeaders['To'][0]) ||
                empty($responseHeaders['Call-ID'][0]) ||
                empty($lastInviteModel['headers']['Via']) ||
                empty($lastInviteModel['headers']['From']) ||
                empty($lastInviteModel['methodForParser'])
            ) {
                return;
            }

            $cseqNum = explode(" ", trim($responseHeaders["CSeq"][0]))[0];

            $ackLine = preg_replace(
                '/^INVITE\s+/i',
                'ACK ',
                $lastInviteModel['methodForParser'],
                1
            );

            if (!$ackLine || $ackLine === $lastInviteModel['methodForParser']) {
                $target = $this->calledNumber ?: '';
                $ackLine = 'ACK ' . $this->sipServerUri($target, false) . ' SIP/2.0';
            }

            $ackModel = [
                "method" => "ACK",
                "methodForParser" => $ackLine,
                "headers" => [
                    "Via" => $lastInviteModel['headers']['Via'],
                    "Max-Forwards" => ["70"],
                    "From" => $lastInviteModel['headers']['From'],
                    "To" => $responseHeaders['To'],
                    "Call-ID" => [$responseHeaders['Call-ID'][0]],
                    "CSeq" => ["{$cseqNum} ACK"],
                    "Content-Length" => ["0"],
                ],
            ];

            $this->sendSipTo(
                $this->host,
                $this->port,
                $this->renderSip($ackModel)
            );
        };

        /*
         * ACK de 200 OK de INVITE.
         * Esse ACK é diálogo confirmado, então usa ackModel().
         * O ackModel precisa gerar Via limpo, não reaproveitar Via da resposta.
         */
        $sendInvite200Ack = function (array $responseHeaders): void {
            $ackModel = $this->ackModel($responseHeaders);

            if (empty($ackModel)) {
                return;
            }

            $ackPacket = $this->renderSip($ackModel);

            $ackHost = $this->host;
            $ackPort = (int)($this->port ?? 5060);

            if (!empty($responseHeaders['Contact'][0])) {
                $contactUri = sip::extractURI($responseHeaders['Contact'][0]);

                if (!empty($contactUri['peer']['host'])) {
                    $ackHost = $contactUri['peer']['host'];
                }

                if (!empty($contactUri['peer']['port'])) {
                    $ackPort = (int)$contactUri['peer']['port'];
                }
            }

            $this->sendSipTo($ackHost, $ackPort, $ackPacket);
        };

        $this->sendSipTo(
            $this->host,
            $this->port,
            $this->renderSip($modelInvite)
        );

        $timeRing = time();
        $inviteSentTime = time();

        for (; ;) {
            if ($this->closing || $this->socket->isClosed()) {
                if (is_callable($this->onFailedCallback)) {
                    return go($this->onFailedCallback, "Conexão encerrada prematuramente");
                }

                return false;
            }

            if (time() - $timeRing > $maxRings) {
                $this->error = true;

                if (is_callable($this->onFailedCallback)) {
                    return go($this->onFailedCallback, "Tempo máximo de toque excedido ({$maxRings}s)");
                }

                return false;
            }

            if (!$firstPacketReceived && (time() - $inviteSentTime > min(15, (int)$maxRings))) {
                $this->error = true;

                if (is_callable($this->onFailedCallback)) {
                    return go(
                        $this->onFailedCallback,
                        "Sem resposta do servidor SIP após " . min(15, (int)$maxRings) . " segundos"
                    );
                }

                return false;
            }

            if ($this->error) {
                if (is_callable($this->onFailedCallback)) {
                    return go($this->onFailedCallback, "Chamada interrompida por erro");
                }

                return false;
            }

            $packet = $this->safeRecvfrom($peer, 1);

            if ($packet === null) {
                continue;
            }

            if ($packet === false || $packet === "") {
                if ($this->socket->isClosed()) {
                    if (is_callable($this->onFailedCallback)) {
                        return go($this->onFailedCallback, "Socket fechado durante espera");
                    }

                    return false;
                }

                continue;
            }

            $receive = sip::parse($packet);

            if (empty($receive['method']) || empty($receive['headers']['Via'])) {
                continue;
            }

            if (!isset($receive["headers"]["Call-ID"])) {
                if (isset($receive["headers"]["i"])) {
                    $receive["headers"]["Call-ID"] = [$receive["headers"]["i"][0]];
                }
            }

            if (isset($receive["headers"]["Call-ID"]) && $receive["headers"]["Call-ID"][0] !== $this->callId) {
                if ($receive["method"] === "OPTIONS") {
                    $this->sendSipTo(
                        $this->host,
                        $this->port,
                        renderMessages::respondOptions($receive["headers"], $this->sipLocalIp, $this->socketPortListen)
                    );
                }

                continue;
            }

            $firstPacketReceived = true;
            $this->currentMethod = $receive["method"];
            $this->lastPacket = $receive;

            if (array_key_exists('Record-Route', $receive["headers"])) {
                $this->route = $receive["headers"]["Record-Route"][0];
            }

            $method = $receive["method"];
            $methodCode = is_numeric($method) ? (int)$method : 0;

            $needAuth = $this->checkAuthHeaders($receive["headers"]);

            if ($needAuth && !$authSent) {
                /*
                 * Antes de reenviar INVITE com Authorization,
                 * fecha a transação anterior com ACK do 401/407.
                 */
                $sendInviteChallengeAck($receive["headers"], $modelInvite);

                $authUri = $this->sipServerUri($this->calledNumber ?: $to, false);

                if ($needAuth === "Proxy-Authorization") {
                    $valueHeader = $receive["headers"]["Proxy-Authenticate"][0] ?? '';

                    if (str_contains($valueHeader, 'realm="')) {
                        $realm = value($valueHeader, 'realm="', '"');
                    } else {
                        $realm = "asterisk";
                    }

                    if (str_contains($valueHeader, 'nonce="')) {
                        $nonce = value($valueHeader, 'nonce="', '"');
                    } else {
                        $nonce = $this->nonce;
                    }

                    if (str_contains($valueHeader, 'qop="')) {
                        $qop = value($valueHeader, 'qop="', '"');
                    } else {
                        $qop = "auth";
                    }

                    $modelInvite["headers"][$needAuth] = [
                        sip::generateResponseProxy(
                            $this->username,
                            $this->password,
                            $realm,
                            $nonce,
                            $authUri,
                            "INVITE",
                            $qop
                        )
                    ];
                }

                if ($needAuth === "Authorization") {
                    $wwwAuthenticate = $receive["headers"]["WWW-Authenticate"][0] ?? '';

                    $nonce = value($wwwAuthenticate, 'nonce="', '"');
                    $realm = value($wwwAuthenticate, 'realm="', '"');

                    $auth = sip::generateAuthorizationHeader(
                        $this->username,
                        $realm,
                        $this->password,
                        $nonce,
                        $authUri,
                        "INVITE"
                    );

                    $modelInvite["headers"][$needAuth] = [$auth];
                }

                $this->csq++;

                /*
                 * INVITE autenticado = nova transação.
                 * Nunca copia Via da resposta.
                 */
                $modelInvite['headers']['Via'] = [
                    "SIP/2.0/UDP {$this->sipViaAddress()};branch=z9hG4bK64d" .
                    bin2hex(secure_random_bytes(8) ?? random_bytes(8)) .
                    ";rport"
                ];

                /*
                 * Nunca copia To da resposta 401/407.
                 * O novo INVITE deve continuar com To sem tag.
                 */
                if (!empty($originalToHeader)) {
                    $modelInvite['headers']['To'] = $originalToHeader;
                }

                $modelInvite['headers']['CSeq'][0] = sprintf("%d INVITE", $this->csq);

                $this->sendSipTo(
                    $this->host,
                    $this->port,
                    $this->renderSip($modelInvite)
                );

                $authSent = true;
                $firstPacketReceived = false;
                $inviteSentTime = time();

                continue;
            }

            $isErrorResponse = is_numeric($method) && $methodCode >= 300 && !in_array($methodCode, [401, 407], true);
            $isAbortRequest = in_array($method, ['CANCEL', 'BYE'], true);

            if ($isErrorResponse || $isAbortRequest) {
                if ($isAbortRequest) {
                    $this->sendSipTo(
                        $this->host,
                        $this->port,
                        renderMessages::respond200OK($receive["headers"], '', $this->sipLocalIp)
                    );
                }

                $this->socket->close();
                $this->error = true;

                if (is_callable($this->onFailedCallback)) {
                    go($this->onFailedCallback, $receive['methodForParser'] ?? "Chamada encerrada ($method)");
                }

                return false;
            }

            if (in_array($receive["method"], $this->progressCodes)) {
                if (is_callable($this->onRingingCallback)) {
                    go($this->onRingingCallback, $this, $receive);
                    $this->onRingingCallback = null;
                }
            }

            if ($receive["method"] === "OPTIONS") {
                $this->sendSipTo(
                    $this->host,
                    $this->port,
                    renderMessages::respondOptions($receive["headers"], $this->sipLocalIp, $this->socketPortListen)
                );
            }

            if (array_key_exists('sdp', $receive)) {
                $remoteAddressAudioDestination = explode(" ", $receive["sdp"]["c"][0])[2] ?? null;
                $remotePortAudioDestination = explode(" ", $receive["sdp"]["m"][0])[1] ?? null;


                if ($remoteAddressAudioDestination && $remotePortAudioDestination) {
                    if (isset($receive["sdp"])) {
                        $this->storeRemoteSdp($receive['sdp']);
                    }
                    $this->audioRemoteIp = $remoteAddressAudioDestination;
                    $this->audioRemotePort = (int)$remotePortAudioDestination;
                    if (is_callable($this->onReceiveSdpCallable)) {
                        go($this->onReceiveSdpCallable, $this);
                    }
                }

                if (!$this->callableRingInvoked) {
                    if ($methodCode > 180 && $methodCode < 200) {
                        if (is_callable($this->onRingingCallback)) {
                            go($this->onRingingCallback, $this, $receive);
                            $this->onRingingCallback = null;
                        }
                    }

                    $this->callableRingInvoked = true;
                }


            }

            if (in_array($receive["method"], $this->successCodes)) {
                $cseqHeader = $receive["headers"]["CSeq"][0] ?? '';
                $cseqMethod = sip::letters($cseqHeader);

                if ($cseqMethod === 'CANCEL') {
                    continue;
                }

                if ($cseqMethod === 'INVITE' && array_key_exists('sdp', $receive)) {
                    break;
                }
            }
        }

        $this->callActive = true;
        $this->headers200 = $receive;

        /*
         * ACK do 200 OK do INVITE.
         * Envia uma vez, para o Contact.
         */
        $sendInvite200Ack($receive["headers"]);

        $remoteAddressAudioDestination = explode(" ", $receive["sdp"]["c"][0])[2] ?? null;
        $remotePortAudioDestination = explode(" ", $receive["sdp"]["m"][0])[1] ?? null;

        if ($remoteAddressAudioDestination && $remotePortAudioDestination) {
            $this->audioRemoteIp = $remoteAddressAudioDestination;
            $this->audioRemotePort = (int)$remotePortAudioDestination;
        }

        $this->storeRemoteSdp($receive['sdp']);

        if (is_callable($this->onAnswerCallback)) {
            go($this->onAnswerCallback, $this);
        }

        for (; ;) {
            if ($this->closing || $this->receiveBye) {
                return false;
            }
            if ($this->byeSent) {
                return false;
            }

            if ($this->error) {
                if (is_callable($this->onHangupCallback)) {
                    go($this->onHangupCallback, $this);
                }

                return false;
            }

            if ($this->socket->isClosed()) {
                return false;
            }

            $res = $this->safeRecvfrom($peer, 1);


            if (!$res) {
                if ($this->socket->isClosed()) {
                    if (is_callable($this->onHangupCallback)) {
                        go($this->onHangupCallback, $this);
                    }
                    return false;
                }

                continue;
            }

            $receive = sip::parse($res);

            if (empty($receive['method']) || empty($receive['headers']['Via'])) {
                continue;
            }

            if (!isset($receive["headers"]["Call-ID"])) {
                if (isset($receive["headers"]["i"])) {
                    $receive["headers"]["Call-ID"] = [$receive["headers"]["i"][0]];
                }
            }

            if (isset($receive["headers"]["Call-ID"]) && $receive["headers"]["Call-ID"][0] !== $this->callId) {
                if ($receive["method"] === "OPTIONS") {
                    $this->sendSipTo(
                        $this->host,
                        $this->port,
                        renderMessages::respondOptions($receive["headers"], $this->sipLocalIp, $this->socketPortListen)
                    );
                }

                continue;
            }

            $this->lastPacket = $receive;

            if (array_key_exists('Record-Route', $receive["headers"])) {
                $this->route = $receive["headers"]["Record-Route"][0];
            }

            if ($receive["method"] === "OPTIONS") {
                $this->sendSipTo(
                    $this->host,
                    $this->port,
                    renderMessages::respondOptions($receive["headers"], $this->sipLocalIp, $this->socketPortListen)
                );

                continue;
            }

            if ($receive["method"] === "NOTIFY" || $receive["method"] === "BYE") {
                $this->callActive = false;
                $this->receiveBye = true;
                $this->unblockCoroutine();

                if ($receive["method"] === "BYE") {
                    $modelOk = renderMessages::respond200OK($receive['headers'], '', $this->sipLocalIp);

                    $byeHost = $peer['address'] ?? $this->host;
                    $byePort = (int)($peer['port'] ?? $this->port ?? 5060);

                    $this->sendSipTo($byeHost, $byePort, $modelOk);

                    if (isset($this->mediaChannel) && $this->mediaChannel instanceof MediaChannel) {
                        $this->mediaChannel->close();
                    }
                }

                if (is_callable($this->onHangupCallback)) {
                    go($this->onHangupCallback, $this, $receive, $peer);
                }

                return false;
            }

            if ($receive['method'] === "200") {
                $cseq = sip::letters($receive["headers"]["CSeq"][0] ?? '');

                if ($cseq === 'BYE') {
                    $this->receiveBye = true;
                    $this->callActive = false;
                    $this->unblockCoroutine();

                    if (is_callable($this->onHangupCallback)) {
                        return go($this->onHangupCallback, $this, $receive, $peer);
                    }

                    return true;
                }

                if ($cseq === "INVITE") {
                    /*
                     * Retransmissão do 200 OK.
                     * Responde ACK de novo, mas somente uma vez por pacote recebido,
                     * e para o Contact correto.
                     */
                    $this->callActive = true;
                    $this->headers200 = $receive;

                    if (isset($receive["sdp"])) {
                        $this->storeRemoteSdp($receive['sdp']);
                    }

                    $sendInvite200Ack($receive["headers"]);

                    continue;
                }
            }

            if ($receive['method'] === 'NOTIFY') {
                $this->receiveBye = true;

                if (is_callable($this->onHangupCallback)) {
                    go($this->onHangupCallback, $this);
                }

                return false;
            }
        }

        $this->receiveBye = true;

        if (is_callable($this->onHangupCallback)) {
            go($this->onHangupCallback, $this);
        }

        print "Call ended 7 Loop passed" . PHP_EOL;

        return true;
    }

    public function modelInvite(string $to, $prefix = "", $options = []): array
    {
        $this->calledNumber = $to;
        if (!$this->username) {
            if ($this->callerId) {
                $this->username = $this->callerId;
            } else {
                $this->username = "100";
            }
        }
        $this->codecRtpMap = [];
        $codecs = array_keys($this->mapLearn);
        foreach ($codecs as $codec) {
            foreach ($this->mapLearn[$codec] as $media) {
                $this->codecRtpMap[] = $media;
            }
        }
        //var_dump( $this->mapLearn);


        $sdp = [
            "v" => ["0"],
            "o" => ["{$this->callerId} 0 0 IN IP4 {$this->localIp}"],
            "s" => [$this->userAgent],
            "c" => ["IN IP4 {$this->localIp}"],
            "t" => ["0 0"],
            "m" => ["audio {$this->rtpSocket->getsockname()['port']} RTP/AVP " . implode(' ', array_keys($this->mapLearn))],
            "a" => [
                'ssrc:' . $this->ssrc . ' cname:' . (!empty($this->callerId) ? $this->callerId : $this->username) . "@{$this->localIp}",
                ...$this->codecRtpMap,
                'ptime:' . $this->packetTime,

                'sendrecv',
            ],
        ];
        $this->sdp = $sdp;
        cli::pcl("audio {$this->rtpSocket->getsockname()['port']} RTP/AVP " . implode(' ', array_keys($this->mapLearn)), 'bold_green');
        $this->ptUse = array_key_first($this->mapLearn);
        $this->rxPt = $this->ptUse;
        $this->txPt = $this->ptUse;
        $this->rxCodecMapper = self::codecMapperFromSdp($this->sdp);
        // Until an answer arrives, preserve the legacy symmetric PT behavior.
        $this->txCodecMapper = $this->rxCodecMapper;
        $parsedLocalSdp = self::getSDPModelCodecs($this->sdp['a']);
        $this->ptTelephoneEvent = $parsedLocalSdp['dtmfCodec']['pt'] ?? array_key_last($this->mapLearn);
        $this->codecName = $parsedLocalSdp['preferredCodec']['name'];
        $this->frequencyCall = $parsedLocalSdp['preferredCodec']['rate'];

        if (strlen($prefix) > 0 && !str_starts_with($to, $prefix)) {
            $to = $prefix . $to;
        }

        $requestHost = $this->domain ? network::extractHost($this->domain) : (string)$this->host;
        $requestUri = sip::renderSipUri($to, $requestHost, $this->port, false);
        $toCall = [
            'user' => $to,
            'peer' => [
                'host' => $this->host ?? $this->domain,
                'port' => $this->port ?? 5060,
            ]
        ];

        $this->calledNumber = $to;
        $settings = [
            "method" => "INVITE",
            "methodForParser" => "INVITE {$requestUri} SIP/2.0",
            "headers" => [
                "Via" => ["SIP/2.0/UDP {$this->sipViaAddress()};branch=z9hG4bK64d" .
                    bin2hex(secure_random_bytes(8) ?? time()) .
                    ";rport"
                ],

                "From" => [sip::renderURI([
                    "user" => !empty($this->callerId) ? $this->callerId : $this->username,
                    "peer" => [
                        "host" => $this->host ?? $this->domain,
                        "port" => $this->port,
                    ],
                    "additional" => ["tag" => bin2hex(secure_random_bytes(10))],
                ])],
                "To" => [sip::renderURI($toCall)],
                "Supported" => ["gruu,replaces"],
                "User-Agent" => [$this->userAgent],
                "Call-ID" => [$this->callId],
                "Allow" => ["INVITE,ACK,BYE,CANCEL,OPTIONS,NOTIFY,MESSAGE,REFER"],
                "Contact" => [sip::renderURI([
                    'user' => (string)$this->username,
                    'peer' => [
                        'host' => $this->sipLocalIp,
                        'port' => $this->socketPortListen,
                    ],
                ])],
                "CSeq" => [$this->csq . " INVITE"],
                "Max-Forwards" => ["70"],
                "Content-Type" => ["application/sdp"],
            ],
            "sdp" => $sdp,
        ];

        $this->inviteHeaders = $settings;

        return $settings;
    }

    public static function getSDPModelCodecs(array $sdpAttributes): array
    {
        $codecMediaLine = "";
        $codecRtpMap = [];
        $defaultChannels = 1;
        $preferredCodec = null;
        $dtmfCodec = null;
        $lineArg = [];
        $rate = 8000;

        // Primeiro, encontrar o codec principal (não telephone-event)
        foreach ($sdpAttributes as $row) {
            if (str_starts_with($row, "rtpmap:")) {
                $pt = value($row, "rtpmap:", " ");
                $rate = explode('/', $row)[1];
                $parts = explode('/', $row);
                if (count($parts) > 2) {
                    $defaultChannels = $parts[2];
                }


                $name = value($row, ' ', '/');
                $codecMediaLine .= "{$pt} ";
                $codecRtpMap[] = $row;

                if ($name !== 'telephone-event') {
                    $preferredCodec = [
                        'pt' => $pt,
                        'rate' => $rate,
                        'sdp' => $row,
                        'name' => $name,
                        'channels' => $defaultChannels
                    ];
                    break; // Encerrar quando encontrar o codec preferencial
                }
            }
        }

        // Adicionar fmtp após o codec principal encontrado
        foreach ($sdpAttributes as $row) {
            if (str_starts_with($row, "fmtp:")) {
                $fmtpPt = value($row, "fmtp:", " ");
                if ($preferredCodec && $fmtpPt === $preferredCodec['pt']) {
                    $codecRtpMap[] = $row; // Inclui o fmtp correspondente
                    $lineArg = self::parseArgumentRtpMap($row);
                }
            }
        }

        // Depois, encontrar o codec `telephone-event` com o mesmo rate
        foreach ($sdpAttributes as $row) {
            if (str_starts_with($row, "rtpmap:") && str_contains($row, 'telephone-event')) {
                $dtmfPt = value($row, "rtpmap:", " ");
                $dtmfRate = explode('/', $row)[1];

                // Verifica se o rate do DTMF é o mesmo do codec preferencial
                if ($dtmfRate == $rate) {
                    $codecMediaLine .= "{$dtmfPt} ";
                    $codecRtpMap[] = $row;
                    $dtmfCodec = [
                        'pt' => $dtmfPt,
                        'rate' => $dtmfRate,
                        'sdp' => $row,
                        'name' => 'telephone-event',
                        'channels' => $defaultChannels
                    ];
                    break; // Encerrar no primeiro DTMF correspondente
                }
            }
        }
        if (!$dtmfCodec) {
            // confere pela ultima vez entao se realmente nao tem
            foreach ($sdpAttributes as $row) {
                if (str_starts_with($row, "rtpmap:") && str_contains($row, 'telephone-event')) {
                    $dtmfPt = value($row, "rtpmap:", " ");
                    $dtmfRate = explode('/', $row)[1];
                    $codecMediaLine .= "{$dtmfPt} ";
                    $codecRtpMap[] = $row;
                    $dtmfCodec = [
                        'pt' => $dtmfPt,
                        'rate' => $dtmfRate,
                        'sdp' => $row,
                        'name' => 'telephone-event',
                        'channels' => $defaultChannels
                    ];
                    break; // Encerrar no primeiro DTMF correspondente
                }
            }
        }


        // Adicionar fmtp após o codec DTMF encontrado
        foreach ($sdpAttributes as $row) {
            if (str_starts_with($row, "fmtp:")) {
                $fmtpPt = value($row, "fmtp:", " ");


                if ($dtmfCodec && $fmtpPt === $dtmfCodec['pt']) {
                    $codecRtpMap[] = $row; // Inclui o fmtp correspondente
                }
            }
        }


        return [
            "codecMediaLine" => trim($codecMediaLine),
            "codecRtpMap" => $codecRtpMap,
            "preferredCodec" => $preferredCodec,
            "dtmfCodec" => $dtmfCodec,
            "config" => $preferredCodec === null ? [] : [
                (int)$preferredCodec['pt'] => $lineArg
            ]
        ];
    }

    /** @return array<int,string> Explicit rtpmap entries keyed by payload type. */
    private static function codecMapperFromSdp(array $sdp): array
    {
        $mapper = [];
        foreach ((array)($sdp['a'] ?? []) as $attribute) {
            if (!is_string($attribute)) {
                continue;
            }
            if (preg_match('/^(?:a=)?rtpmap\s*:\s*(\d+)\s+([^\s]+)\s*$/i', trim($attribute), $match) !== 1) {
                continue;
            }
            $mapper[(int)$match[1]] = $match[2];
        }
        return $mapper;
    }

    /** @return list<int> */
    private static function audioPayloadTypesFromSdp(array $sdp): array
    {
        foreach ((array)($sdp['m'] ?? []) as $mediaLine) {
            if (!is_string($mediaLine)) {
                continue;
            }
            $parts = preg_split('/\s+/', trim(preg_replace('/^m=/i', '', $mediaLine)));
            if (($parts[0] ?? '') !== 'audio') {
                continue;
            }
            return array_values(array_map('intval', array_slice($parts, 3)));
        }
        return [];
    }

    /** @return array{pt:int,name:string,rate:int,channels:int}|null */
    private static function preferredAudioCodecFromSdp(array $sdp): ?array
    {
        $explicitMapper = self::codecMapperFromSdp($sdp);
        $payloadTypes = self::audioPayloadTypesFromSdp($sdp);
        if ($payloadTypes === []) {
            $payloadTypes = array_keys($explicitMapper);
        }

        foreach ($payloadTypes as $pt) {
            $descriptor = $explicitMapper[$pt] ?? self::RTP_AVP_STATIC_CODECS[$pt] ?? null;
            if ($descriptor === null) {
                continue;
            }
            $parts = explode('/', $descriptor);
            $name = strtoupper(trim((string)($parts[0] ?? '')));
            if ($name === '' || $name === 'TELEPHONE-EVENT') {
                continue;
            }
            return [
                'pt' => (int)$pt,
                'name' => $name,
                'rate' => max(1, (int)($parts[1] ?? 8000)),
                'channels' => max(1, (int)($parts[2] ?? 1)),
            ];
        }
        return null;
    }

    /** @param array{pt:int,name:string,rate:int,channels:int} $remoteCodec */
    private function applyRemoteAnswerNegotiation(array $remoteCodec): void
    {
        $this->txPt = $remoteCodec['pt'];
        $this->txCodecMapper[$remoteCodec['pt']] ??= implode('/', [
            $remoteCodec['name'],
            $remoteCodec['rate'],
            $remoteCodec['channels'],
        ]);
        $this->ptUse = $this->txPt;
        $this->codecName = $remoteCodec['name'];
        $this->frequencyCall = $remoteCodec['rate'];
        $this->defaultChannels = $remoteCodec['channels'];

        foreach ($this->rxCodecMapper as $pt => $descriptor) {
            $parts = explode('/', $descriptor);
            $name = strtoupper(trim((string)($parts[0] ?? '')));
            $rate = max(1, (int)($parts[1] ?? 8000));
            $channels = max(1, (int)($parts[2] ?? 1));
            if ($name === $remoteCodec['name'] && $rate === $remoteCodec['rate'] && $channels === $remoteCodec['channels']) {
                $this->rxPt = (int)$pt;
                return;
            }
        }
    }

    public static function parseArgumentRtpMap(string $line): array
    {
        if (!str_contains($line, 'fmtp')) return [];
        $result = [];
        $parts = explode(' ', $line)[1];
        $matches = []; //maxplaybackrate=24000;sprop-maxcapturerate=24000;maxaveragebitrate=64000;useinbandfec=1
        preg_match_all('/(\w+)=(\d+)/', $parts, $matches);
        foreach ($matches[1] as $key => $value) {
            $result[$value] = $matches[2][$key];
        }
        return $result;
    }

    /**
     * @throws RandomException
     */


    public static function renderURI(array $uriData): string
    {
        return sip::renderURI($uriData);
    }

    public function checkAuthHeaders(array $headers)
    {
        foreach ($this->headersNeedAuthorization as $header => $value) {
            if (array_key_exists($header, $headers)) {
                return $value;
            }
        }
        return false;
    }

    /**
     * Construir array ACK para confirmar recepção de 200 OK
     *
     * O ACK é enviado em resposta a uma resposta 2xx bem-sucedida.
     *
     * Estrutura:
     * [
     *     "method" => "ACK",
     *     "methodForParser" => "ACK sip:contact@host:port SIP/2.0",
     *     "headers" => [
     *         "Via" => [...com novo branch...],
     *         "From" => [...idêntico ao INVITE...];tag=..."],
     *         "To" => [...idêntico ao INVITE...];tag=server-tag"],
     *         "Call-ID" => [...idêntico...],
     *         "CSeq" => ["N ACK"]
     *     ]
     * ]
     *
     * Características importantes:
     * - Call-ID, From, To DEVEM ser idênticos ao INVITE
     * - CSeq usa mesmo número do INVITE, mas método muda para "ACK"
     * - ACK NÃO espera resposta (nenhuma resposta para ACK é válida)
     * - Sem corpo/SDP no ACK
     *
     * @param array $headers Headers da resposta 200 OK (extraidos com sip::parse())
     *
     * @return array Array de sinalização ACK pronto para envio
     *
     * @example
     * // Após receber 200 OK
     * $received = sip::parse($response);
     * $modelAck = $phone->ackModel($received['headers']);
     * $phone->socket->sendto($host, $port, sip::renderSolution($modelAck));
     *
     * @see SIGNALING_ARRAYS.md para documentação completa
     */
    public function ackModel(array $headers): array
    {
        $ruleNeed = [
            "Contact",
            "CSeq",
            "From",
            "To",
            "Call-ID",
        ];

        foreach ($ruleNeed as $rule) {
            if (!array_key_exists($rule, $headers) || empty($headers[$rule][0])) {
                return [];
            }
        }

        $contactUri = trunkController::extractURI($headers["Contact"][0]);
        $uriFrom = trunkController::extractURI($headers["From"][0]);
        $uriTo = trunkController::extractURI($headers["To"][0]);

        $ackInt = explode(" ", trim($headers["CSeq"][0]))[0];
        $this->csq = $ackInt;

        $contactHost = $contactUri["peer"]["host"] ?? $this->host;
        $contactPort = $contactUri["peer"]["port"] ?? 5060;
        $contactUser = $contactUri["user"] ?? $uriTo["user"];

        $requestUri = sip::renderSipUri($contactUser, $contactHost, $contactPort, false);

        $branch = "z9hG4bK64d" . bin2hex(secure_random_bytes(8) ?? random_bytes(8));

        $base = [
            "method" => "ACK",
            "methodForParser" => "ACK {$requestUri} SIP/2.0",
            "headers" => [
                "Via" => [
                    "SIP/2.0/UDP {$this->sipViaAddress()};branch={$branch};rport"
                ],

                "Max-Forwards" => [
                    "70"
                ],

                "From" => [
                    trunkController::renderURI([
                        "user" => $uriFrom["user"],
                        "peer" => [
                            "host" => $uriFrom["peer"]["host"],
                            "port" => $uriFrom["peer"]["port"] ?? null,
                        ],
                        "additional" => [
                            "tag" => $uriFrom["additional"]["tag"] ?? "",
                        ],
                    ])
                ],

                "To" => [
                    trunkController::renderURI([
                        "user" => $uriTo["user"],
                        "peer" => [
                            "host" => $uriTo["peer"]["host"],
                            "port" => $uriTo["peer"]["port"] ?? null,
                        ],
                        "additional" => [
                            "tag" => $uriTo["additional"]["tag"] ?? "",
                        ],
                    ])
                ],

                "Call-ID" => [
                    $headers["Call-ID"][0]
                ],

                "CSeq" => [
                    "{$this->csq} ACK"
                ],

                "Content-Length" => [
                    "0"
                ],
            ],
        ];

        if (array_key_exists("Record-Route", $headers) && !empty($headers["Record-Route"])) {
            $base["headers"]["Route"] = $headers["Record-Route"];
        }

        return $base;
    }

    public static function extractURI($line): array
    {
        return sip::extractURI($line);
    }

    public function unblockCoroutine(): bool
    {
        return $this->receiveBye = false;
    }

    public bool|MediaChannel $mediaChannel;
    public bool $waitingSilence = false;
    public bool $waitingSilenceType = true;
    public float $waitingSilenceTime = 1.0;
    public float $waitingSilenceStart = 0;

    public bool $waitingSilenceSuccess = false;
    private float $waitingSilenceVoiceThreshold = 1.1;

    public function waitSilence(bool $waitSilence = true, float $time = 1.0): bool
    {
        if ($time <= 0) {
            throw new \InvalidArgumentException('O tempo de espera deve ser maior que zero');
        }

        $this->waitingSilence = true;
        $this->waitingSilenceType = $waitSilence;
        $this->waitingSilenceTime = $time;
        $this->waitingSilenceStart = microtime(true);
        $this->waitingSilenceSuccess = false;

        while ($this->waitingSilence) {
            co::sleep(min(0.01, $this->packetTime / 1000));

            if (!$this->waitingSilence) {
                break;
            }
            if ($this->receiveBye || !$this->callActive) {
                $this->completeWaitSilence(false);
                break;
            }

            // Para espera por voz, `time` é o timeout máximo. A espera por
            // silêncio é concluída pelos frames RTP após silêncio contínuo.
            if (
                !$this->waitingSilenceType
                && microtime(true) - $this->waitingSilenceStart >= $this->waitingSilenceTime
            ) {
                $this->completeWaitSilence(false);
            }
        }

        $success = $this->waitingSilenceSuccess;
        $this->waitingSilenceSuccess = false;
        return $success;
    }

    private function processWaitSilenceFrame(string $pcmData, int $sampleRate, int $channels): void
    {
        if (!$this->waitingSilence) {
            return;
        }

        try {
            $volume = $this->volumeAverage($pcmData, $sampleRate, $channels);
        } catch (\Throwable) {
            $volume = 0.0;
        }

        $now = microtime(true);
        $hasVoice = $volume >= $this->waitingSilenceVoiceThreshold;

        if ($this->waitingSilenceType) {
            if ($hasVoice) {
                $this->waitingSilenceStart = $now;
                return;
            }
            if ($now - $this->waitingSilenceStart >= $this->waitingSilenceTime) {
                $this->completeWaitSilence(true);
            }
            return;
        }

        if ($hasVoice) {
            $this->completeWaitSilence(true);
        } elseif ($now - $this->waitingSilenceStart >= $this->waitingSilenceTime) {
            $this->completeWaitSilence(false);
        }
    }

    private function completeWaitSilence(bool $success): void
    {
        $this->waitingSilenceSuccess = $success;
        $this->waitingSilence = false;
        $this->waitingSilenceType = true;
        $this->waitingSilenceStart = 0.0;
        $this->waitingSilenceTime = 1.0;
    }

    public mixed $onPacketOnTimeoutMediaCallable = null;

    public function onPacketOnTimeoutMedia(callable $callback): void
    {
        $this->onPacketOnTimeoutMediaCallable = $callback;
    }


    public function receiveMedia(): void
    {

        Coroutine::create(function () {
            if ($this->socketInUse !== false) return false;


            $this->socketInUse = 'yes';
            $this->mediaChannel->setPacketTime($this->packetTime);
            $this->mediaChannel->setReceiveOnlyMode(true);


            $this->remoteIp = $this->audioRemoteIp;
            $this->remotePort = $this->audioRemotePort;


            cli::pcl("Proxy de áudio iniciado na porta " . $this->mediaChannel->socket->getsockname()['address'] . ":" . $this->mediaChannel->socket->getsockname()['port']);
            $this->lastSpeakTime = microtime(true);
            $this->speakWaitSequence = [];
            $this->waitingEnd = 0;
            $this->startSpeak = false;

            $this->error = false;
            $this->callActive = true;
            $this->receiveBye = false;


            //$this->mediaChannel = new MediaChannel($this->rtpSocket, $this->callId);
            if (is_callable($this->onPacketOnTimeoutMediaCallable))
                $this->mediaChannel->packetOnTimeout($this->onPacketOnTimeoutMediaCallable);


            if ($this->vadEnabled) {
                $this->mediaChannel->enableVAD();
                if ($this->vadTimeoutSeconds > 0) {
                    $this->mediaChannel->setVADTimeout($this->vadTimeoutSeconds);
                }



                $this->mediaChannel->onVadChange(function ($isVoiceActive, $energy, $id) {
                    //cli::pcl("{$id} Nivel de energia: {$energy}", !$isVoiceActive ? 'bold_red' : 'bold_green');
                });
                $this->mediaChannel->setVadRegistrationThreshold(15.51);

            }


            $this->mediaChannel->portList = $this->audioReceivePort;
            $this->mediaChannel->onDtmfCallable = $this->onDtmfCallable;
            $txPt = (int)($this->txPt ?? $this->ptUse);
            $rxPt = (int)($this->rxPt ?? $this->ptUse);
            $fallbackMapping = strtoupper(implode('/', [
                $this->codecName,
                $this->frequencyCall,
                $this->defaultChannels
            ]));
            $this->mediaChannel->txCodecMapper = $this->txCodecMapper !== []
                ? $this->txCodecMapper
                : [$txPt => $fallbackMapping];
            $this->mediaChannel->rxCodecMapper = $this->rxCodecMapper !== []
                ? $this->rxCodecMapper
                : [$rxPt => $fallbackMapping];
            // Legacy mapper follows the legacy PT field: both describe TX.
            $this->mediaChannel->codecMapper = $this->mediaChannel->txCodecMapper;
            $this->mediaChannel->registerPtCodecs($this->mediaChannel->codecMapper);
            $audioAttributes = [];
            foreach ((array)($this->sdpReceived['a'] ?? []) as $value) {
                $commons = explode(' ', $value);
                foreach ($commons as $common) {
                    $parts = explode(':', $common);
                    if ($parts[0] == 'ssrc') {
                        $audioAttributes['ssrc'] = $parts[1];

                    }
                }
            }
            $parser = trunkController::getSDPModelCodecs((array)($this->sdpReceived['a'] ?? []));

            if (array_key_exists('config', $parser) && array_key_exists('stereo', $parser['config'][$txPt] ?? [])) {
                $this->defaultChannels = $parser['config'][$txPt]['stereo'] ? 2 : 1;
            } else {
                $this->defaultChannels = 1;
            }

            $this->mediaChannel->addMember([
                'address' => $this->audioRemoteIp,
                'port' => $this->audioRemotePort,
                'codec' => $this->codecName,
                'pt' => $txPt,
                'txPt' => $txPt,
                'rxPt' => $rxPt,
                'txCodecMapper' => $this->mediaChannel->txCodecMapper,
                'rxCodecMapper' => $this->mediaChannel->rxCodecMapper,
                'timestamp' => time(),
                'config' => $parser['config'][$txPt] ?? [],
                'ssrc' => $audioAttributes['ssrc'] ?? $this->ssrc,
                'frequency' => $this->frequencyCall,
                'channels' => $this->defaultChannels,

            ]);


            $this->mediaChannel->recordingEnabled = $this->audioRecordingEnabled;


            $this->mediaChannel->onReceive(function (rtpc $rtpc, array $peer, MediaChannel $channel, rtpChannel $rtpChannel) {
                if (empty($rtpc->payloadRaw)) {
                    return;
                }

                $packetCodecName = $channel->resolveCodecNameFromPt($rtpc->payloadType);

                if (strtoupper($packetCodecName) === 'TELEPHONE-EVENT') {
                    return;
                }

                $hasPcmCallback = is_callable($this->onReceivePcmCallback);

                $needsPcm =
                    $channel->recordingEnabled ||
                    $this->waitingSilence ||
                    $this->vadEnabled ||
                    $hasPcmCallback;

                if (!$needsPcm) {
                    return;
                }

                $targetId = $peer['address'] . ':' . $peer['port'];

                $pcmData = null;

                switch (strtoupper($packetCodecName)) {
                    case 'PCMU':
                        $pcmData = decodePcmuToPcm($rtpc->payloadRaw);
                        break;

                    case 'PCMA':
                        $pcmData = decodePcmaToPcm($rtpc->payloadRaw);
                        break;

                    case 'G729':
                        if (!isset($channel->members[$targetId]['bcg729Channel'])) {
                            $memberRtp = $channel->members[$targetId]['rtpChannel'];
                            // Dynamic TX PTs do not initialize rtpChannel's
                            // legacy PT=18 codec property. Cache the decoder in
                            // the member slot already handled by close().
                            $channel->members[$targetId]['bcg729Channel'] = $memberRtp->payloadType === rtpChannel::PAYLOAD_G729
                                ? $memberRtp->bcg729Channel
                                : new \bcg729Channel();
                        }
                        $pcmData = $channel->members[$targetId]['bcg729Channel']->decode($rtpc->payloadRaw);
                        break;

                    case 'GSM':
                        $decoder = $channel->members[$targetId]['gsmDecoder'] ?? null;
                        if ($decoder instanceof \gsmChannel && strlen($rtpc->payloadRaw) % 33 === 0) {
                            $pcmData = $decoder->decode($rtpc->payloadRaw);
                        }
                        break;

                    case 'OPUS':
                        if (!isset($this->mediaChannel->members[$targetId]['opusDecoder'])) {
                            return;
                        }



                        $pcmData = $this->mediaChannel
                            ->members[$targetId]['opusDecoder']
                            ->decode($rtpc->payloadRaw);
                        break;

                    case 'L16':
                        $pcmData = decodeL16ToPcm($rtpc->payloadRaw);
                        break;

                    default:
                        return;
                }

                if (empty($pcmData)) {
                    return;
                }

                $frequencyPacket = null;

                if ($channel->recordingEnabled || $hasPcmCallback) {
                    $frequencyPacket = $channel->getFrequencyFromPtCodec($rtpc->payloadType);
                }

                $channelsPacket = max(1, (int)($this->mediaChannel->members[$targetId]['channels'] ?? 1));
                if ($this->vadEnabled) {
                    $this->processVAD($pcmData, $targetId);
                }
                if ($this->waitingSilence) {
                    $frequencyPacket ??= $channel->getFrequencyFromPtCodec($rtpc->payloadType);

                    $this->processWaitSilenceFrame($pcmData, (int)$frequencyPacket, $channelsPacket);
                }

                if ($channel->recordingEnabled) {
                    $ssrc = $rtpc->ssrc;
                    $frequencyPacket ??= $channel->getFrequencyFromPtCodec($rtpc->payloadType);

                    $this->bufferWriteSound[$ssrc] ??= [];
                    $this->bufferWriteSound[$ssrc][$frequencyPacket] ??= [];
                    $this->bufferWriteSound[$ssrc][$frequencyPacket][$channelsPacket] ??= '';

                    $this->bufferWriteSound[$ssrc][$frequencyPacket][$channelsPacket] .= $pcmData;
                }



                if ($hasPcmCallback) {
                    $frequencyPacket ??= $channel->getFrequencyFromPtCodec($rtpc->payloadType);

                    ($this->onReceivePcmCallback)(...)(
                        $pcmData,
                        $peer,
                        $this,
                        $packetCodecName,
                        $frequencyPacket
                    );
                }

            });
            $this->mediaChannel->onStart(function () {
                if (!is_callable($this->audioFileHandle)) {
                    return;
                }

                $this->runAudioTransmissionLoop();
                if (!$this->byeSent and !$this->receiveBye) {
                    $this->bye();
                }
            });
            $this->mediaChannel->start();
            $this->mediaChannel?->block();
        });
    }

    /**
     * Envia um frame por ptime usando um relógio monotônico.
     *
     * O deadline representa o início do próximo ciclo. Assim, o tempo gasto em
     * leitura, resample, encode, cache e sendto é descontado do sleep seguinte,
     * sem alterar a timeline RTP mantida por rtpChannel.
     */
    private function runAudioTransmissionLoop(): void
    {
        $pacedPacketTime = $this->packetTime;
        $nextDeadlineNs = hrtime(true);
        $audioWasSuspended = false;

        try {
            while ($this->mediaChannel->active) {
                $currentPacketTime = $this->packetTime;
                if ($currentPacketTime !== $pacedPacketTime) {
                    $pacedPacketTime = $currentPacketTime;
                    $nextDeadlineNs = hrtime(true);
                }

                $canSendAudio =
                    !$this->mediaChannel->dtmfInUse &&
                    $this->audioFileHandle instanceof \Closure &&
                    !empty($this->audioRemoteIp) &&
                    !empty($this->audioRemotePort);

                if ($canSendAudio) {
                    if ($audioWasSuspended) {
                        // DTMF (ou ausência temporária do peer) não vira débito do
                        // áudio e, portanto, não deve gerar uma rajada ao retornar.
                        $nextDeadlineNs = hrtime(true);
                        $audioWasSuspended = false;
                    }

                    try {
                        ($this->audioFileHandle)([
                            'address' => $this->audioRemoteIp,
                            'port' => $this->audioRemotePort,
                        ], $this);
                    } catch (\Throwable $e) {
                        cli::pcl("[AUDIO-LOOP-ERROR] " . $e->getMessage(), 'red');
                        $this->stopAudioFile();
                    }
                } else {
                    $nextDeadlineNs = hrtime(true);
                    $audioWasSuspended = true;
                }

                if (!$this->mediaChannel->active) {
                    break;
                }

                // setPacketTime() pode ter sido chamado enquanto o callback cedia
                // a coroutine em I/O. A nova cadência começa a partir de agora.
                $currentPacketTime = $this->packetTime;
                if ($currentPacketTime !== $pacedPacketTime) {
                    $pacedPacketTime = $currentPacketTime;
                    $nextDeadlineNs = hrtime(true);
                }

                $nextDeadlineNs += $pacedPacketTime * 1_000_000;
                $remainingNs = $nextDeadlineNs - hrtime(true);

                if ($remainingNs > 0) {
                    // Swoole exige pelo menos 1 ms. Arredondar para esse piso evita
                    // busy-loop; eventual overshoot é absorvido pelo próximo deadline.
                    \Swoole\Coroutine::sleep(max(0.001, $remainingNs / 1_000_000_000));
                } else {
                    // A preparação/UDP pode ter enviado o frame só depois do
                    // deadline. Reiniciar imediatamente enviaria o seguinte em
                    // rajada. Preserve todo o áudio e retome após um ptime;
                    // a lacuna real não remove samples da timeline RTP.
                    $nextDeadlineNs = hrtime(true);
                    \Swoole\Coroutine::sleep($pacedPacketTime / 1000);
                    $nextDeadlineNs += $pacedPacketTime * 1_000_000;
                }
            }
        } finally {
            $this->stopAudioFile();
        }
    }

    public function setPacketTime(int $packetTime): void
    {
        if ($packetTime <= 0) {
            throw new \InvalidArgumentException('Packet time deve ser maior que 0');
        }
        $this->validatePacketTimeForCodec($packetTime, (string)($this->codecName ?? ''));

        $this->configuredPacketTime = $packetTime;
        $this->applyConfiguredPacketTime();
    }

    private function applyConfiguredPacketTime(): void
    {
        $effectivePacketTime = $this->resolvePacketTimeWithinRemoteLimit(
            $this->configuredPacketTime,
            $this->remoteMaxPacketTime,
            (string)($this->codecName ?? '')
        );

        if ($effectivePacketTime === null) {
            return;
        }

        $this->packetTime = $effectivePacketTime;

        if (isset($this->mediaChannel) && $this->mediaChannel instanceof MediaChannel) {
            $this->mediaChannel->setPacketTime($effectivePacketTime);
        }
    }

    public function getPacketTime(): int
    {
        return $this->packetTime;
    }

    public function getConfiguredPacketTime(): int
    {
        return $this->configuredPacketTime;
    }

    public function getRemoteMaxPacketTime(): ?int
    {
        return $this->remoteMaxPacketTime;
    }

    private function storeRemoteSdp(array $sdp): void
    {
        $this->sdpReceived = array_merge(['a' => [], 'm' => [], 'c' => []], $sdp);
        $attributes = is_array($this->sdpReceived['a'] ?? null) ? $this->sdpReceived['a'] : [];
        $this->remoteMaxPacketTime = $this->extractMaxPacketTime($attributes);
        $this->txCodecMapper = self::codecMapperFromSdp($this->sdpReceived);
        if ($this->rxCodecMapper !== []) {
            $remoteCodec = self::preferredAudioCodecFromSdp($this->sdpReceived);
            if ($remoteCodec !== null) {
                $this->applyRemoteAnswerNegotiation($remoteCodec);
            }
        }
        $this->applyConfiguredPacketTime();
    }

    private function extractMaxPacketTime(array $attributes): ?int
    {
        foreach ($attributes as $attribute) {
            if (!is_string($attribute)) {
                continue;
            }
            if (preg_match('/^(?:a=)?maxptime\s*:\s*(\d+(?:\.\d+)?)\s*$/i', trim($attribute), $match) !== 1) {
                continue;
            }

            $maxPacketTime = (int)floor((float)$match[1]);
            return $maxPacketTime > 0 ? $maxPacketTime : null;
        }
        return null;
    }

    private function resolvePacketTimeWithinRemoteLimit(int $configuredPacketTime, ?int $remoteMaxPacketTime, string $codec): ?int
    {
        if ($remoteMaxPacketTime === null || $configuredPacketTime <= $remoteMaxPacketTime) {
            return $configuredPacketTime;
        }

        $codec = strtoupper(trim($codec));
        if ($codec === 'OPUS') {
            $candidates = array_values(array_filter(
                self::OPUS_PACKET_TIMES_MS,
                static fn(int $ptime): bool => $ptime <= $remoteMaxPacketTime
            ));
            return $candidates === [] ? null : max($candidates);
        }
        if ($codec === 'G729') {
            $compatiblePacketTime = intdiv($remoteMaxPacketTime, 10) * 10;
            return $compatiblePacketTime > 0 ? $compatiblePacketTime : null;
        }
        if ($codec === 'GSM') {
            $compatiblePacketTime = intdiv($remoteMaxPacketTime, 20) * 20;
            return $compatiblePacketTime > 0 ? $compatiblePacketTime : null;
        }
        return $remoteMaxPacketTime;
    }

    private function validatePacketTimeForCodec(int $packetTime, string $codec): void
    {
        $codec = strtoupper(trim($codec));
        if ($codec === 'OPUS' && !in_array($packetTime, self::OPUS_PACKET_TIMES_MS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Packet time de %d ms não é suportado por Opus; use 5, 10, 20, 40, 60, 80, 100 ou 120 ms',
                $packetTime
            ));
        }
        if ($codec === 'G729' && $packetTime % 10 !== 0) {
            throw new \InvalidArgumentException('Packet time de G.729 deve ser múltiplo de 10 ms');
        }
        if ($codec === 'GSM' && $packetTime % 20 !== 0) {
            throw new \InvalidArgumentException('Packet time de GSM deve ser múltiplo de 20 ms');
        }
    }

    private bool $adaptationEnabled = false;
    private array $qualityReports = [];
    private int $adaptationCheckInterval = 50;
    private int $packetsProcessed = 0;
    public array $registeredIds = [];
    private array $lastVadActivity = [];
    private int $vadTimeoutSeconds = 10;
    private float $vadRegistrationThreshold = 1;

    public $onVadChangeCallable = null;
    public bool $isVoiceActive = false;
    public bool $vadEnabled = false;

    // Novo sistema VAD com threshold adaptativo
    private float $vadMinEnergy = 2.0;
    private float $vadNoiseFloor = 0.0;
    private float $vadSpeechThreshold = 0.0;
    private array $vadEnergyHistory = [];
    private int $vadHistorySize = 100;
    private int $vadHangoverFrames = 15;
    private int $vadCurrentHangover = 0;
    private int $vadFrameCounter = 0;
    private int $vadReportInterval = 50;
    private int $vadNoiseEstimateInterval = 200;
    private int $vadNoiseFrameCounter = 0;
    public bool $recordingEnabled = false;
    private string $recordingPath = '';
    private array $dtmfLastEvent = [];
    private int $dtmfDebounceMs = 100;
    private array $dtmfPacketCache = []; // Cache para detectar retransmissões RFC 4733
    public array $audioMetrics = [
        'total_packets' => 0,
        'lost_packets' => 0,
        'avg_energy' => 0.0,
        'voice_time' => 0.0,
        'silence_time' => 0.0,
    ];

    public function processVAD(string $pcmData, ...$extra): void
    {
        if (!$this->vadEnabled) {
            return;
        }

        $energy = volumeAverage($pcmData);
        $idFrom = $extra[0] ?? $this->callId;

        // Adiciona energia ao histórico
        $this->vadEnergyHistory[] = $energy;
        if (count($this->vadEnergyHistory) > $this->vadHistorySize) {
            array_shift($this->vadEnergyHistory);
        }

        // Atualiza estimativa de ruído periodicamente
        $this->vadNoiseFrameCounter++;
        if ($this->vadNoiseFrameCounter >= $this->vadNoiseEstimateInterval) {
            $this->vadNoiseFrameCounter = 0;
            $this->updateNoiseEstimate();
        }

        // Calcula threshold adaptativo
        $adaptiveThreshold = max($this->vadMinEnergy, $this->vadSpeechThreshold);

        $wasActive = $this->isVoiceActive;

        // Detecção de voz
        if ($energy > $adaptiveThreshold) {
            $this->isVoiceActive = true;
            $this->vadCurrentHangover = $this->vadHangoverFrames;
        } else if ($this->vadCurrentHangover > 0) {
            $this->vadCurrentHangover--;
            $this->isVoiceActive = true;
        } else {
            $this->isVoiceActive = false;
        }

        // Registra atividade
        if ($this->isVoiceActive) {
            if (!isset($this->registeredIds[$idFrom])) {
                $this->registeredIds[$idFrom] = true;
            }
            $this->lastVadActivity[$idFrom] = microtime(true);
            $this->audioMetrics['voice_time'] += $this->packetTime / 1000;
        } else {
            $this->audioMetrics['silence_time'] += $this->packetTime / 1000;
        }

        // Atualiza métricas
        $this->audioMetrics['avg_energy'] = $this->audioMetrics['avg_energy'] * 0.95 + $energy * 0.05;

        // Report periódico
        $this->vadFrameCounter++;
        $periodicReport = $this->vadFrameCounter >= $this->vadReportInterval;
        if ($periodicReport) {
            $this->vadFrameCounter = 0;
        }

        // Callback apenas em mudança de estado ou report periódico
        if ($wasActive !== $this->isVoiceActive || $periodicReport) {
            if (is_callable($this->onVadChangeCallable)) {
                go($this->onVadChangeCallable, $this->isVoiceActive, $energy, $extra[0]);
            }
        }
    }

    private function updateNoiseEstimate(): void
    {
        if (count($this->vadEnergyHistory) < 50) {
            return;
        }

        // Pega os 30% menores valores (ruído de fundo)
        $sorted = $this->vadEnergyHistory;
        sort($sorted);
        $noiseCount = (int)(count($sorted) * 0.3);
        $noiseSamples = array_slice($sorted, 0, max(1, $noiseCount));

        // Calcula média e desvio padrão do ruído
        $this->vadNoiseFloor = array_sum($noiseSamples) / count($noiseSamples);

        // Threshold é ruído + margem de segurança
        $this->vadSpeechThreshold = $this->vadNoiseFloor * 2.5;
    }

    public function onVadChange(callable $callback): void
    {
        $this->onVadChangeCallable = $callback;
    }


    public function getBuffer(): string|StringObject
    {
        $channels = [];


        foreach ($this->bufferWriteSound as $ssrc => $freq) {
            foreach ($freq as $freqPacket => $chansGroup) {
                foreach ($chansGroup as $nchan => $pcm) {
                    $channels[] = $pcm;
                }
            }
        }

        $uni='';
        $count = count($channels);

        if ($count > 1) {
            $uni = interleavePcmStereo($channels[0], $channels[1]);
        } elseif ($count == 1) {
            $uni = $channels[0];
        }


        return swoole_string((string)$uni);
    }


    public ?Closure $onDtmfCallable;

    public function onKeyPress(callable $callback): void
    {
        $this->onDtmfCallable = $callback;
    }

    public function addMember(string $username): void
    {
        if (!in_array($username, $this->members)) {
            $this->members[] = $username;
        }
    }

    public function onBeforeAudioBuild(Closure $closure): void
    {
        $this->onBuildAudio = $closure;
    }

    /**
     * Construir array CANCEL para cancelar chamada pendente
     *
     * CANCEL é usado para terminar uma chamada que ainda está em fase de setup
     * (quando toca mas o destino ainda não respondeu com 200 OK).
     *
     * Estrutura:
     * [
     *     "method" => "CANCEL",
     *     "methodForParser" => "CANCEL sip:called@host SIP/2.0",
     *     "headers" => [
     *         "Via" => [...novo branch...],
     *         "From" => [...],
     *         "To" => [...],
     *         "Call-ID" => [...idêntico...],
     *         "CSeq" => ["N CANCEL"]  // Mesmo número do INVITE
     *     ]
     * ]
     *
     * Características:
     * - CSeq usa MESMO número do INVITE, mas método muda para "CANCEL"
     * - Call-ID deve ser idêntico ao INVITE original
     * - Sem corpo/SDP
     * - Servidor responde com 200 OK, depois envia 487 Request Terminated ao destino
     *
     * @param bool|string $called Número chamado (opcional, usa this->calledNumber)
     *
     * @return array Array de sinalização CANCEL pronto para envio
     *
     * @example
     * // Para cancelar uma chamada em andamento
     * $modelCancel = $phone->getModelCancel();
     * $phone->socket->sendto($host, $port, sip::renderSolution($modelCancel));
     *
     * @see SIGNALING_ARRAYS.md para documentação completa
     */
    public function getModelCancel($called = false): array
    {
        if ($called) {
            $this->calledNumber = $called;
        }
        $requestUri = $this->sipServerUri((string)$this->calledNumber, false);
        $model = [
            "method" => "CANCEL",
            "methodForParser" => "CANCEL {$requestUri} SIP/2.0",
            "headers" => [
                "Via" => ["SIP/2.0/UDP {$this->sipViaAddress()};branch=z9hG4bK-" . bin2hex(secure_random_bytes(4))],
                "From" => [sip::renderURI([
                    'user' => (string)$this->username,
                    'peer' => ['host' => (string)$this->host, 'port' => $this->port],
                    'additional' => ['tag' => bin2hex(secure_random_bytes(8))],
                ])],
                "Max-Forwards" => ["70"],
                "User-Agent" => ["{$this->userAgent}"],
                "Contact" => [sip::renderURI([
                    "user" => $this->username,
                    "peer" => [
                        "host" => $this->sipLocalIp,
                        "port" => $this->socketPortListen,
                    ],
                ])],
                "To" => [sip::renderURI([
                    'user' => (string)$this->calledNumber,
                    'peer' => ['host' => (string)$this->host, 'port' => $this->port],
                ])],
                "Call-ID" => [$this->callId],
                "CSeq" => [$this->csq . " CANCEL"],
            ],
        ];
        if ($this->route)
            $model["headers"]["Record-Route"][0] = $this->route;
        return $model;

    }

    public function cancel(): void
    {
        $this->cancelSent = true;
        $this->sendSipTo($this->host, $this->port, $this->renderSip($this->getModelCancel()));
    }

    public function getBufferWriteSound(): array
    {
        return $this->bufferWriteSound;
    }

    public function getBufferWriteSoundBySsrc(int $ssrc): array
    {
        return $this->bufferWriteSound[$ssrc] ?? [];
    }

    public function close(): void
    {
        // Evitar múltiplas chamadas e garantir reentrância
        if ($this->closing) {
            return;
        }
        $this->closing = true;
        $this->error = true;
        $this->receiveBye = true;
        $this->callActive = false;
        $this->blockSpeak = false;
        $this->stopAudioFile();


        foreach ($this->idTimers as $id => $timer) {
            Timer::clear($id);
        }

        cli::pcl("Iniciando fechamento Call-ID: {$this->callId}", 'yellow');


        // Envia BYE se houver chamada ativa (fazer antes de fechar sockets)


        // Para o mediaChannel se existir
        if ($this->mediaChannel) {
            try {
                $this->mediaChannel->active = false;
                $this->mediaChannel->unblock();
                $this->mediaChannel->close();
            } catch (\Throwable $e) {
                // Ignora erros
            }
        }

        // Para o proxy media se estiver ativo
        if ($this->proxyMediaActive) {
            try {
                $this->stopProxyMedia();
            } catch (\Throwable $e) {
            }
        }

        // Fecha todos os sockets
        if (!$this->preserveSockets) {
            foreach ($this->socketsList as $socket) {
                try {
                    if ($socket instanceof SocketMutable && !$socket->isClosed()) {
                        $socket->close();
                    }
                } catch (\Throwable $e) {
                }
            }

            // Fecha sockets principais
            try {
                if ($this->socket && !$this->socket->isClosed()) {
                    $this->socket->close();
                }
            } catch (\Throwable $e) {
            }

            try {
                if ($this->rtpSocket && !$this->rtpSocket->isClosed()) {
                    $this->rtpSocket->close();
                }
            } catch (\Throwable $e) {
            }
        }

        // Limpa callbacks
        $this->onFailedCallback = null;
        $this->onAnswerCallback = null;
        $this->onRingingCallback = null;
        $this->onHangupCallback = null;

        $this->onDtmfCallable = null;
        $this->dtmfCallbacks = [];


        // Limpa outras propriedades grandes


        $this->bufferWriteSound = [];
        $this->box = [];
        $this->members = [];
        $this->callActive = false;


    }


    public function onHangup(callable $callback): void
    {
        $this->onHangupCallback = $callback;
    }


    public function bye(): void
    {
        if ($this->byeSent) return;
        $this->byeSent=true;
        if (empty($this->headers200)) {
            return;
        }
        $this->sendSipTo(
            $this->host,
            $this->port,
            $this->renderSip(renderMessages::generateBye($this->headers200['headers'], $this->sipLocalIp, $this->socketPortListen))
        );
        $this->mediaChannel->close();
    }

    public function modelBye(): array
    {
        return renderMessages::generateBye($this->headers200['headers'], $this->sipLocalIp, $this->socketPortListen);

    }

    public function addListener(mixed $receiveIp, string $receivePort): void
    {
        $this->listeners[] = [
            'address' => $receiveIp,
            'port' => $receivePort,
        ];
    }

    public function defineTimeout(int $time): void
    {
        $this->closeCallInTime = $this->timeoutCall + $time;

        $this->idTimers[] = Timer::after($time * 1000, function () {
            if ($this->closing || $this->error) {
                return;
            }
            try {
                $this->bye();
            } catch (\Throwable $e) {
                cli::pcl($e->getMessage());
            }
            $this->close();
        });
    }

    public function setCallId(string $callId): void
    {
        $this->callId = $callId;
    }

    public function setCallerId(string $callerId): void
    {
        $this->callerId = $callerId;
    }

    public function declareVolume($ipPort, $user, $c): void
    {
        cli::pcl("Declarando volume para {$ipPort} {$user} {$c}", 'bold_yellow');
        $validate = explode(":", $ipPort);
        if (count($validate) < 2) {
            return;
        }
        if ((int)$validate[1] < 1024) {
            return;
        }
        $this->originVolumes[$ipPort] = $user;
        $this->volumeCodec[$ipPort] = $c;
        if (!array_key_exists($ipPort, $this->volumesAverage)) {
            $this->volumesAverage[$ipPort] = [
                "base" => 0,
                "polo" => 0,
            ];
        } else {
            $this->volumesAverage[$ipPort][$user] = 0;
        }
    }


    public function unRegister(): bool
    {
        if (strlen($this->username) < 1) {

            return false;
        }
        if (strlen($this->password) < 1) {
            return false;
        }
        if (!$this->isRegistered) {
            return false;
        }

        $this->isRegistered=false;
        $maxWait = 1.5;
        if ($this->registerCount > 3) {
            return false;
        }
        $res = false;
        $modelRegister = $this->modelRegister(0);
        unset($modelRegister['headers']['Contact']);


        $renderSolution = $this->renderSip($modelRegister);
        $startTimer = time();



        $sent=false;
        if (!$this->reopenSipSocket()) {
            cli::pcl("Falha ao iniciar socket SIP para deslogar", 'red');
            return false;
        }
        while (time() - $startTimer < $maxWait) {
            if ($this->socket->isClosed()) {
                if (!$this->reopenSipSocket()) {
                    cli::pcl("Falha ao reiniciar socket SIP para deslogar", 'red');
                    return false;
                }
            } else {
               $this->sendSipTo($this->host, $this->port, $renderSolution);
               $sent=$this->socket->recvfrom($peer, 1);
                if ($sent === false) {
                    $this->socket->close();
                } else {
                    $sent=true;
                    break;
                }
            }
        }



        $startTimer = time();
        for (; ;) {

            try {


                if (!$sent)
                $res = $this->safeRecvfrom($peer, 1);
                else {
                    $res=$sent;
                    $sent=false;
                }

                if ($res === null) {
                    cli::pcl("Socket ocupado por outra corrotina, assumimos que não podemos esperar resposta aqui");
                    // Socket ocupado por outra corrotina, assumimos que não podemos esperar resposta aqui
                    return true;
                }
            } catch (\Throwable $e) {
                cli::pcl("Erro ao receber resposta do servidor durante deslogagem: " . $e->getMessage(), 'red');
                continue;
            }
            $elapsed = time() - $startTimer;
            if ($elapsed > $maxWait) {
                cli::pcl("Timeout No Response in {$maxWait} seconds On UnRegister", 'red');
                return false;
            }
            if ($res === false) {
                if (time() - $startTimer > $maxWait) {
                    return false;
                }
                continue;
            }
            $receive = sip::parse($res);

            if (empty($receive['headers']['CSeq'])) {
                continue;
            }
            $cseq = sip::letters($receive["headers"]["CSeq"][0]);
            if ($cseq == 'OPTIONS') continue;
            if ($receive['method'] == '401') {
                $needAuth = $this->checkAuthHeaders($receive["headers"]);

                if ($needAuth == "Proxy-Authorization") {
                    $valueHeader = $receive["headers"]["Proxy-Authenticate"][0] ?? '';

                    $realm = str_contains($valueHeader, 'realm="')
                        ? value($valueHeader, 'realm="', '"')
                        : "asterisk";

                    $nonce = str_contains($valueHeader, 'nonce="')
                        ? value($valueHeader, 'nonce="', '"')
                        : $this->nonce;

                    $qop = str_contains($valueHeader, 'qop="')
                        ? value($valueHeader, 'qop="', '"')
                        : "auth";

                    if (str_contains($valueHeader, 'stale=true') || !$nonce) {
                        continue;
                    }

                    $this->nonce = $nonce;

                    $modelRegister["headers"][$needAuth][0] = sip::generateResponseProxy(
                        $this->username,
                        $this->password,
                        $realm,
                        $nonce,
                        $this->sipServerUri(),
                        "REGISTER",
                        $qop
                    );
                } elseif ($needAuth == "Authorization") {
                    $wwwAuthenticate = $receive["headers"]["WWW-Authenticate"][0] ?? '';

                    $nonce = value($wwwAuthenticate, 'nonce="', '"');
                    $realm = value($wwwAuthenticate, 'realm="', '"');

                    if (str_contains($wwwAuthenticate, 'stale=true') || !$nonce) {
                        return false;
                    }

                    $this->nonce = $nonce;

                    $modelRegister["headers"][$needAuth][0] = sip::generateAuthorizationHeader(
                        $this->username,
                        $realm,
                        $this->password,
                        $nonce,
                        $this->sipServerUri(),
                        "REGISTER"
                    );
                }

                unset($modelRegister['headers']['Session-Expires']);

                $this->csq++;
                $modelRegister['headers']['CSeq'][0] = $this->csq . ' REGISTER';

                $modelRegister['headers']['Contact'][0] = '*';
                $modelRegister['headers']['Expires'][0] = '0';

                if (isset($modelRegister['headers']['To'][0])) {
                    $modelRegister['headers']['To'][0] = preg_replace(
                        '/;tag=[^;>\s]+/i',
                        '',
                        $modelRegister['headers']['To'][0]
                    );
                }

                if (isset($modelRegister['headers']['Via'][0])) {
                    $modelRegister['headers']['Via'][0] = preg_replace(
                        '/branch=z9hG4bK-[^;\s]+/i',
                        'branch=z9hG4bK-' . bin2hex(random_bytes(8)),
                        $modelRegister['headers']['Via'][0]
                    );
                }

                $renderSolution = $this->renderSip($modelRegister);

                $this->sendSipTo($this->host, $this->port, $renderSolution);
                $res = $this->socket->safeRecvfrom($peer, 1);
                if ($res) {
                    $receive = sip::parse($res);
                    if ($receive['method'] == '200') {
                        $this->csq++;
                        $this->isRegistered = false;
                        $this->callActive = false;

                        cli::pcl("Deslogado com sucesso", 'green');
                        return true;
                    }
                }
            }

            if ($receive['method'] == '200') {
                $this->csq++;
                $this->isRegistered = false;
                $this->callActive = false;
                return true;
            }


        }
    }

    public function register(int $maxWait = 5): bool
    {
        if (strlen($this->username) < 1) {

            return false;
        }
        if (strlen($this->password) < 1) {
            return false;
        }


        if ($this->registerCount > 3) {
            return false;
        }
        $res = false;
        $modelRegister = $this->modelRegister();
        $renderSolution = $this->renderSip($modelRegister);
        $startTimer = time();
        $this->sendSipTo($this->host, $this->port, $renderSolution);
        for (; ;) {
            $elapsed = time() - $startTimer;
            if ($elapsed > $maxWait) {
                cli::pcl("Falha ao registrar: tempo limite excedido", 'red');
                return false;
            }
            $res = $this->safeRecvfrom($peer, 1);
            if ($res === null) {
                // Socket ocupado, não podemos ler aqui
                return true;
            }
            if ($res === false) {
                if (time() - $startTimer > $maxWait) {
                    return false;
                }
            }
            $receive = sip::parse($res);
            if (empty($receive['headers']['CSeq'])) {
                cli::pcl($res, 'red');
                continue;
            }
            $cseq = sip::letters($receive["headers"]["CSeq"][0]);
            if ($cseq == 'OPTIONS') continue;
            if ($receive['method'] == '401') {
                $needAuth = $this->checkAuthHeaders($receive["headers"]);
                if ($needAuth == "Proxy-Authorization") {
                    $valueHeader = $receive["headers"]["Proxy-Authenticate"][0];
                    if (str_contains($valueHeader, 'realm="')) {
                        $realm = value($valueHeader, 'realm="', '"');
                    } else {
                        $realm = "asterisk";
                    }
                    if (str_contains($valueHeader, 'nonce="')) {
                        $nonce = value($valueHeader, 'nonce="', '"');
                    } else {
                        $nonce = $this->nonce;
                    }
                    if (str_contains($valueHeader, 'qop="')) {
                        $qop = value($valueHeader, 'qop="', '"');
                    } else {
                        $qop = "auth";
                    }
                    $isStale = str_contains($valueHeader, 'stale=true');
                    if ($isStale || !$nonce) {
                        continue;
                    }
                    $this->nonce = $nonce;
                    $modelRegister["headers"][$needAuth][0] = sip::generateResponseProxy($this->username, $this->password, $realm, $nonce, $this->sipServerUri(), "REGISTER", $qop);
                } else if ($needAuth == "Authorization") {
                    $wwwAuthenticate = $receive["headers"]["WWW-Authenticate"][0];
                    $nonce = value($wwwAuthenticate, 'nonce="', '"');
                    $realm = value($wwwAuthenticate, 'realm="', '"');
                    $isStale = str_contains($wwwAuthenticate, 'stale=true');
                    if ($isStale || !$nonce) {
                        cli::pcl("Erro ao registrar, stale=true e nonce ausente", 'bold_red');
                        return false;
                    }
                    $this->nonce = $nonce;
                    $modelRegister["headers"][$needAuth][0] = sip::generateAuthorizationHeader($this->username, $realm, $this->password, $nonce, $this->sipServerUri(), "REGISTER");
                }
                $renderSolution = $this->renderSip($modelRegister);
                $this->sendSipTo($this->host, $this->port, $renderSolution);
            }
            if ($receive['method'] == '200') {
                $this->csq++;
                $this->isRegistered = true;
                cli::pcl($receive['methodForParser'], 'bold_green');
                return true;
            }
        }
    }

    /**
     * Construir array REGISTER para registrar no servidor SIP
     *
     * Estrutura:
     * [
     *     "method" => "REGISTER",
     *     "methodForParser" => "REGISTER sip:host SIP/2.0",
     *     "headers" => [
     *         "Via" => ["..."],
     *         "From" => ["<sip:username@host>;tag=..."],
     *         "To" => ["<sip:username@host>"],
     *         "Call-ID" => ["..."],
     *         "CSeq" => ["N REGISTER"],
     *         "Contact" => ["<sip:username@ip:port>"],
     *         "Expires" => ["3600"],
     *         ...
     *     ]
     * ]
     *
     * Se autenticação for requerida:
     * - Servidor responde 401 ou 407 com desafio Digest
     * - Método register() trata a autenticação adicionando Authorization header
     * - CSeq é incrementado
     * - INVITE é reenviado
     *
     * @return array Array de sinalização REGISTER
     *
     * @see register() para lógica de autenticação Digest
     * @see SIGNALING_ARRAYS.md para documentação completa
     */
    public function modelRegister($expire = 120): array
    {
        if ($this->domain) {
            $registerHost = network::extractHost($this->domain);
            $fpee = $registerHost;
            $fpp = $this->port;
        } else {
            $registerHost = (string)$this->host;
            $fpee = $this->sipLocalIp;
            $fpp = $this->socketPortListen;
        }
        $registerUri = sip::renderSipUri('', $registerHost, $this->port, false);
        $toLine = sip::renderURI([
            'user' => (string)$this->username,
            'peer' => ['host' => $registerHost, 'port' => $this->port],
        ]);
        return [
            "method" => "REGISTER",
            "methodForParser" => "REGISTER {$registerUri} SIP/2.0",
            "headers" => [
                "Via" => ["SIP/2.0/UDP {$this->sipViaAddress()};branch=z9hG4bK-" . bin2hex(secure_random_bytes(8)).';rport'],
                "From" => [sip::renderURI([
                    "user" => $this->username,
                    "peer" => [
                        "host" => $fpee,
                        "port" => $fpp,
                    ],
                    "additional" => ["tag" => bin2hex(secure_random_bytes(8))],
                ])],
                "To" => [$toLine],
                "Max-Forwards" => ["70"],
                "Call-ID" => [$this->callId],
                "CSeq" => [$this->csq . " REGISTER"],
                "Contact" => [sip::renderURI([
                    'user' => (string)$this->username,
                    'peer' => ['host' => $this->sipLocalIp, 'port' => $this->socketPortListen],
                ])],
                "User-Agent" => [$this->userAgent],
                "Expires" => ["$expire"],
                "Allow" => ["INVITE, ACK, CANCEL, OPTIONS, BYE, REFER, NOTIFY, MESSAGE, INFO, UPDATE"],
                "Content-Length" => ["0"],
            ],
        ];
    }

    public function saveBufferToWavFile(string $caminho, string $audioBuffer): void
    {

        $chans=1;
        if ($this->stereoMode) $chans=2;
        $audio = waveHead3(strlen($audioBuffer), $this->frequencyCall, $chans, 1) . $audioBuffer;
        Coroutine::writeFile($caminho, $audio);
    }

    public function registerByeRecovery(array $byeClient, array $destination, $socketPreserve): void
    {
        $this->byeRecovery = [
            "model" => $byeClient,
            "client" => $destination,
            "socket" => $socketPreserve,
        ];
    }

    /**
     * Verifica se o proxy media já está ativo para esta chamada
     */
    public function isProxyMediaActive(): bool
    {
        return $this->proxyMediaActive && !empty($this->currentProxyId);
    }

    /**
     * Obtém o ID do proxy ativo
     */
    public function getProxyId(): ?string
    {
        return $this->currentProxyId;
    }

    /**
     * Força a parada do proxy media
     */
    public function stopProxyMedia(): void
    {
        if ($this->currentProxyId) {
            $rpcClient = new rpcClient();
            $rpcClient->rpcDelete($this->currentProxyId);
            $rpcClient->close();
            cli::pcl("ProxyMedia {$this->currentProxyId} parado forçadamente", 'red');
        }
        $this->proxyMediaActive = false;
        $this->currentProxyId = null;
    }

    public function clearAudioBuffer(): void
    {
        $this->bufferAudio = "";
        $this->bufferWriteSound = [];

    }

    public function registerDtmfCallback(string $dtmf, callable $callback): void
    {
        $this->dtmfCallbacks[$dtmf] = $callback;
    }

    public function resetTimeout(): void
    {
        $this->timeoutCall = time();
    }

    /**
     * Construir e enviar REFER para transferir chamada ativa
     *
     * REFER instrui o peer a transferir a chamada para outro número.
     * Usado para IVR, atendimento, etc.
     *
     * Estrutura do array REFER:
     * [
     *     "method" => "REFER",
     *     "methodForParser" => "REFER sip:current@host SIP/2.0",
     *     "headers" => [
     *         "Via" => [...novo branch...],
     *         "From" => ["<sip:username@host>;tag=..."],
     *         "To" => ["<sip:current_dest@host>"],
     *         "Call-ID" => ["...idêntico..."],
     *         "CSeq" => ["N REFER"],
     *         "Refer-To" => ["sip:new_dest@host"],  // ← Destino transferência
     *         "Referred-By" => ["sip:username@host"],
     *         "Event" => ["refer"],
     *         "Contact" => ["<sip:username@localIp>"],
     *         "Content-Length" => ["0"]
     *     ]
     * ]
     *
     * Fluxo de Transferência:
     * 1. Cliente A chama Cliente B (conectado)
     * 2. Cliente A envia REFER para B, indicando: "transfira para C"
     * 3. Servidor responde 202 Accepted
     * 4. Servidor (ou B) inicia nova chamada para C
     * 5. Quando C atende, servidor desconecta A (ou aguarda BYE de A)
     * 6. B fica livre para nova chamada
     *
     * @param string $to Número destino da transferência (ex: "5511888888888")
     *
     * @return bool|null Sucesso no envio do REFER via UDP
     *
     * @example
     * // Transferir a chamada atual para outro número
     * $phone->transfer('5511888888888');
     *
     * @see transferGroup() para transferência para grupo de agentes
     * @see SIGNALING_ARRAYS.md para documentação completa da estrutura
     */
    public function transfer(string $to): ?bool
    {
        $originTo = $this->calledNumber;
        $modelRefer = [
            "method" => "REFER",
            "methodForParser" => 'REFER ' . $this->sipServerUri((string)$originTo, false) . ' SIP/2.0',
            "headers" => [
                "Via" => ["SIP/2.0/UDP {$this->sipViaAddress()};branch=z9hG4bK-" . bin2hex(secure_random_bytes(4))],
                "From" => [sip::renderURI([
                    'user' => (string)$this->username,
                    'peer' => ['host' => (string)$this->host, 'port' => $this->port],
                    'additional' => ['tag' => bin2hex(secure_random_bytes(8))],
                ])],
                "To" => [sip::renderURI([
                    'user' => (string)$originTo,
                    'peer' => ['host' => (string)$this->host, 'port' => $this->port],
                ])],
                "Call-ID" => [$this->callId],
                "Event" => ["refer"],
                "CSeq" => [$this->csq . " REFER"],
                "Contact" => [sip::renderURI([
                    'user' => (string)$this->username,
                    'peer' => ['host' => $this->sipLocalIp, 'port' => $this->socketPortListen],
                ])],
                "Refer-To" => [$this->sipServerUri($to, false)],
                "Referred-By" => [$this->sipServerUri((string)$this->username, false)],
                "Content-Length" => ["0"],
            ],
        ];
        return (bool)$this->sendSipTo($this->host, $this->port, $this->renderSip($modelRefer));
    }

    public function transferGroup(string $groupName, $retry = 0)
    {
        if ($retry > 3) {
            return false;
        }
        $retry++;
        $nameFile = \Extension\plugins\utils::baseDir() . '/groups.json';
        $groups = json_decode(file_get_contents($nameFile), true);
        if (!isset($groups[$groupName])) {
            echo "⚠ Grupo {$groupName} não encontrado.\n";
            return false;
        }
        $group = $groups[$groupName];
        $agents = $group['agents'];
        $connectionsFile = \Extension\plugins\utils::baseDir() . 'connections.json';
        $callsFile = \Extension\plugins\utils::baseDir() . 'calls.json';
        $excluded = [];
        $callsContent = json_decode(file_get_contents($callsFile), true);
        foreach ($callsContent as $callId => $data) {
            $excluded = array_merge($excluded, array_keys($data));
        }
        $timeout = 35;
        $startTime = time();
        do {
            $connections = json_decode(@file_get_contents($connectionsFile), true);
            if (!is_array($connections)) {
                $connections = [];
            }
            foreach ($agents as $idAgent => $agent) {
                if (!array_key_exists($agent, $connections)) {
                    unset($agents[$idAgent]);
                    continue;
                }
                if ($agent === $this->username) {
                    unset($agents[$idAgent]);
                    continue;
                }
                if (in_array($agent, $excluded)) {
                    unset($agents[$idAgent]);
                    continue;
                }
            }
            if (!empty($agents)) {
                break;
            }
            interruptibleSleep(0.1, $this->callActive);
        } while (time() - $startTime < $timeout);
        if (empty($agents)) {
            $this->resetTimeout();
            $baseDir = \Extension\plugins\utils::baseDir();


            return $this->transferGroup($groupName, $retry);
        }
        foreach ($agents as $agent) {
            (function ($agent) {
                echo "✅ Transferindo chamada para {$agent}...\n";
                $this->transfer($agent);
            })($agent);
        }
        cli::pcl("Já saiu do loop");
    }


    /**
     * Define o callback para processamento do áudio recebido
     * @param callable $param Função com a assinatura function(string $pcmData, array $peer, trunkController $phone): void {}
     */
    public function onReceivePcm(callable $param)
    {
        $this->onReceivePcmCallback = $param;
    }

    public $onReceivePcmCallback;

    public function stopAudioFile(): void
    {
        $this->audioPlayback?->close();
        $this->audioPlayback = null;
        $this->registerAudioEvent(static function () {
        });
    }


    /**
     * Extrai PCM bruto e informações do arquivo WAV
     * @param string $wavFile Caminho do arquivo WAV
     * @return array ['pcm' => string, 'sampleRate' => int, 'bitsPerSample' => int, 'numChannels' => int, 'chunkSize' => int]
     */
    public function loadWavFile(string $wavFile): array
    {
        if (!file_exists($wavFile)) {
            throw new \Exception("Arquivo WAV não encontrado: $wavFile");
        }

        $wavContent = file_get_contents($wavFile);

        // Extrair informações do header WAV
        $wavInfo = [
            'riff' => substr($wavContent, 0, 4),
            'fileSize' => unpack('V', substr($wavContent, 4, 4))[1],
            'wave' => substr($wavContent, 8, 4),
            'audioFormat' => unpack('v', substr($wavContent, 20, 2))[1], // 1 = PCM
            'numChannels' => unpack('v', substr($wavContent, 22, 2))[1],
            'sampleRate' => unpack('V', substr($wavContent, 24, 4))[1],
            'byteRate' => unpack('V', substr($wavContent, 28, 4))[1],
            'blockAlign' => unpack('v', substr($wavContent, 32, 2))[1],
            'bitsPerSample' => unpack('v', substr($wavContent, 34, 2))[1],
        ];

        // Encontrar chunk "data"
        $dataPos = strpos($wavContent, 'data', 36);
        if ($dataPos === false) $dataPos = 36;

        $wavInfo['dataSize'] = unpack('V', substr($wavContent, $dataPos + 4, 4))[1];
        $headerSize = $dataPos + 8;

        // Extrair PCM bruto
        $rawPcm = substr($wavContent, $headerSize);


        // Calcular um frame PCM completo no ptime configurado.
        $bytesPerSample = $wavInfo['bitsPerSample'] / 8;
        $chunkSize = (int)round(
            $wavInfo['sampleRate']
            * ($this->packetTime / 1000)
            * $wavInfo['numChannels']
            * $bytesPerSample
        );

        return [
            'pcm' => $rawPcm,
            'sampleRate' => $wavInfo['sampleRate'],
            'bitsPerSample' => $wavInfo['bitsPerSample'],
            'numChannels' => $wavInfo['numChannels'],
            'chunkSize' => $chunkSize,
        ];
    }

    public function getCid()
    {
        return $this->cid;
    }

    public bool $audioRecordingEnabled = false;

    public bool $audioMemorySharingEnabled = true;

    /**
     * Modo de resample para reprodução de áudio (defineAudioFile).
     *  - 'resampler' (padrão): usa resampler() — rápido, menor latência/CPU.
     *  - 'resample' : usa resample() — alta qualidade (kaiser_best, q=10), mais CPU.
     */
    public string $resampleMode = 'resampler';

    /**
     * Define qual modo de resample será usado no fluxo de áudio.
     * Valores aceitos: 'resampler' (default) ou 'resample'.
     */
    public function setResampleMode(string $mode): void
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['resampler', 'resample'], true)) {
            $mode = 'resampler';
        }
        $this->resampleMode = $mode;
    }

    /**
     * Helper interno: aplica o resample conforme o modo escolhido.
     * Em modo 'resample' aplica filtro de alta qualidade; em 'resampler' usa o caminho rápido.
     * `options` aceita ao menos: input_channels, output_channels.
     */
    private function doResample(string $pcm, int $srcRate, int $dstRate, array $options = [], ?string $mode = null): string
    {
        $inCh = (int)($options['input_channels'] ?? 1);
        $outCh = (int)($options['output_channels'] ?? $inCh);
        $hasc = 'não';
        if ($inCh === 1 && $outCh === 2) {
            $pcm = monoToStereo($pcm);
            $inCh = 2;
            $hasc = 'sim';
        } elseif ($inCh === 2 && $outCh === 1) {
            $pcm = stereoToMono($pcm);
            $inCh = 1;
            $hasc = 'sim';
        }

        if ($srcRate === $dstRate && $inCh === $outCh) {
            return $pcm;
        }
        $options['input_channels'] = $inCh;
        $options['output_channels'] = $outCh;
        if (($mode ?? $this->resampleMode) === 'resample') {
            $opts = $options + [
                'resample_filter'  => 'kaiser_best',
                'resample_quality' => 10,
            ];
            return resample($pcm, $srcRate, $dstRate, $opts);
        }

        // Keep channel conversion separate from rate conversion. The native
        // resample() FIR can buffer short frames and return an empty string;
        // padding that result would replace actual stereo audio with silence.
        if ($inCh !== $outCh) {
            return resample($pcm, $srcRate, $dstRate, [
                'input_channels'  => $inCh,
                'output_channels' => $outCh,
            ]);
        }
        if ($inCh === 2 && $outCh === 2) {
            // PcmBuffer consumes PCM16LE frames and applies the same FIR to
            // both interleaved channels while keeping their histories separate.
            $pcm = substr($pcm, 0, strlen($pcm) - (strlen($pcm) % 4));
            $buffer = new \PcmBuffer($srcRate, 2);
            $buffer->append($pcm);
            $buffer->resample($dstRate);
            $output = $buffer->toString();
            // flush() replaces the buffer payload with the FIR tail.
            $buffer->flush();
            return $output . $buffer->toString();
        }
        return resampler($pcm, $srcRate, $dstRate);
    }

    public function enableAudioMemorySharing(): void
    {
        $this->audioMemorySharingEnabled = true;
    }

    public function disableAudioMemorySharing(): void
    {
        $this->audioMemorySharingEnabled = false;
    }

    public function enableAudioRecording(): void
    {
        $this->audioRecordingEnabled = true;
    }


    public function enableVAD(): void
    {
        $this->vadEnabled = true;
        $this->vadTimeoutSeconds=10;
    }

    public bool $loopAudioFile = true;

    public function autoReplayMedia(bool $option = true): void
    {
        $this->loopAudioFile = $option;

    }


    public static array $sharedAudioCache = [];

    public function defineAudioFile(string $audioFile): void
    {
        clearstatcache(true, $audioFile);
        $fileMTime = file_exists($audioFile) ? filemtime($audioFile) : 0;
        // One file identity per definition, including same-size replacements
        // within the filesystem's mtime resolution. Never stat/hash per RTP.
        $cacheKey = hash('sha256', (realpath($audioFile) ?: $audioFile) . '_' . $fileMTime
            . '_' . (is_file($audioFile) ? hash_file('sha256', $audioFile) : ''));
        $fromCache = false;

        if ($this->audioMemorySharingEnabled && isset(self::$sharedAudioCache[$cacheKey])) {
            $cache = self::$sharedAudioCache[$cacheKey];
            $infoFile = $cache['infoFile'];
            $audioData = $cache['audioData'];
            $audioLen = $cache['audioLen'];
            $fromCache = true;
        }

        if (!$fromCache) {
            try {
                \libspech\Sip\secureAudioVoip($audioFile);
            } catch (\Exception $e) {
                cli::pcl("Error defining audio file: " . $e->getMessage());
                return;
            }
            $infoFile = \libspech\Sip\getInfoAudio($audioFile);

            $tags = \libspech\Sip\wavChunks($audioFile);

            $idDataTag = array_find_key($tags, fn($tag) => $tag['id'] === 'data');

            if ($idDataTag === null) {
                cli::pcl("Error: WAV data chunk not found");
                return;
            }

            $dataOffset = $tags[$idDataTag]['data'];

            $fileData = file_get_contents($audioFile);

            if ($fileData === false) {
                cli::pcl("Error reading audio file");
                return;
            }

            $audioData = substr($fileData, $dataOffset);
            unset($fileData);

            $audioLen = strlen($audioData);

            if ($this->audioMemorySharingEnabled) {
                self::$sharedAudioCache[$cacheKey] = [
                    'infoFile' => $infoFile,
                    'audioData' => $audioData,
                    'audioLen' => $audioLen,
                ];
            }
        }

        $chunkSize = \libspech\Sip\calculateChunkSize(
            $infoFile['rate'], $infoFile['numChannels'], $infoFile['bitDepth'], $this->packetTime
        );
        $configuredPacketTime = $this->packetTime;
        $this->audioPlayback?->close();
        $playback = new \libspech\Audio\AudioFilePlayback(
            $audioData, $infoFile, $cacheKey,
            fn(string $pcm, array $info, array $context): string => $this->prepareAudioFileFrame($pcm, $info, $context)
        );
        $this->audioPlayback = $playback;
        $canConsume = function (array $context, \libspech\Audio\AudioFilePlayback $source): bool {
            $member = $this->mediaChannel->members[$context['endpoint']] ?? [];
            return $this->audioPlayback === $source && $this->callActive && $this->mediaChannel->active
                && !$this->mediaChannel->dtmfInUse && $this->packetTime === $context['ptime']
                && strtoupper($this->codecName) === $context['codec'] && $this->frequencyCall === $context['frequency']
                && (int)($member['channels'] ?? 1) === $context['channels']
                && ($member['config'] ?? []) === $context['config'] && $this->resampleMode === $context['resampleMode']
                && $this->loopAudioFile === $context['loop'] && $this->audioMemorySharingEnabled === $context['sharing'];
        };
        $this->registerAudioEvent(function ($peer, trunkController $phone) use ($playback, $canConsume, $infoFile, &$chunkSize, &$configuredPacketTime) {
            if (empty($this->callActive)) {
                $this->stopAudioFile();
                return;
            }
            if ($configuredPacketTime !== $phone->getPacketTime()) {
                $configuredPacketTime = $phone->getPacketTime();
                $chunkSize = \libspech\Sip\calculateChunkSize(
                    $infoFile['rate'], $infoFile['numChannels'], $infoFile['bitDepth'], $configuredPacketTime
                );
            }
            $id = $peer['address'] . ':' . $peer['port'];
            $member = $this->mediaChannel->members[$id] ?? null;
            if (!(($member['rtpChannel'] ?? null) instanceof rtpChannel)) {
                return;
            }
            $context = [
                'endpoint' => $id,
                'codec' => strtoupper($phone->codecName),
                'frequency' => $phone->frequencyCall,
                'channels' => (int)($member['channels'] ?? 1),
                'chunkSize' => $chunkSize,
                'ptime' => $configuredPacketTime,
                'config' => $member['config'] ?? [],
                'resampleMode' => $phone->resampleMode,
                'sharing' => $phone->audioMemorySharingEnabled,
                'loop' => $phone->loopAudioFile,
            ];
            $payload = $playback->nextPayload($context, $canConsume);
            // pop() can yield during startup/underflow. Do not emit stale media
            // after teardown, DTMF, replacement or reconfiguration during that wait.
            if ($payload === null || !$this->mediaChannel->active || !$this->callActive
                || $this->audioPlayback !== $playback || $this->mediaChannel->dtmfInUse) {
                return;
            }
            $packet = $this->mediaChannel->members[$id]['rtpChannel']->buildAudioPacket($payload);
            $this->mediaChannel->socket->sendto($peer['address'], $peer['port'], $packet);
        });
    }

    /** Conversion runs in the bounded preparer; encoder state stays at consumption. */
    private function prepareAudioFileFrame(string $pcm, array $info, array $context): string
    {
        $codec = $context['codec'];
        $channels = $codec === 'GSM' ? 1 : $context['channels'];
        $rate = match ($codec) {
            'PCMA', 'PCMU', 'G729', 'GSM' => 8000,
            'OPUS' => 48000,
            default => $context['frequency'],
        };
        if ($info['numChannels'] !== $channels) {
            $pcm = $this->doResample($pcm, $info['rate'], $info['rate'], [
                'input_channels' => $info['numChannels'], 'output_channels' => $channels,
            ], $context['resampleMode']);
        }
        if ($info['rate'] !== $rate) {
            $pcm = $this->doResample($pcm, $info['rate'], $rate, [
                'input_channels' => $channels, 'output_channels' => $channels,
            ], $context['resampleMode']);
            if ($pcm === '') {
                throw new \RuntimeException('Audio resampler returned no PCM frame; use resampler mode or a WAV at the codec sample rate');
            }
        }
        $bytes = max(1, (int)round($rate * $context['ptime'] / 1000)) * $channels * 2;
        $pcm = strlen($pcm) > $bytes ? substr($pcm, 0, $bytes) : str_pad($pcm, $bytes, "\x00");
        return match ($codec) {
            'PCMA' => encodePcmToPcma($pcm),
            'PCMU' => encodePcmToPcmu($pcm),
            'G729', 'GSM', 'OPUS' => $pcm,
            'L16' => pcmLeToBe($pcm),
            default => throw new \RuntimeException('Unsupported audio file codec: ' . $codec),
        };
    }

    /**
     * Callback a ser executado ao receber uma SDP (Session Description Protocol).
     *  - false (padrão): nenhuma função será chamada ao receber uma SDP.
     *  - callable: função a ser chamada com os dados da SDP recebida.
     */
    public mixed $onReceiveSdpCallable = false;

    /**
     * Sets a callback to be executed when an SDP (Session Description Protocol) message is received.
     *
     * @param Closure $param A callable function to handle the received SDP.
     *                       The callback should define the logic to process the SDP message.
     *
     * @return void
     *
     * @example
     * $object->onSdpReceived(function ($sdp) {
     *     // Process the received SDP here
     *     echo "Received SDP: " . $sdp;
     * });
     */
    public function onSdpReceived(Closure $param): void
    {
        $this->onReceiveSdpCallable = $param;
    }

    public int|float $voiceActivityTimeout=10;
    public function voiceActivityTimeout(int|float $time): void
    {
        $this->voiceActivityTimeout = $time;
    }


    private function generateEmptyWavFile(string $path, int $durationSec): void
    {
        $fakeData = str_repeat(chr(0), $durationSec * 8000);
        file_put_contents($path, waveHead(strlen($fakeData), 8000, 1, 1) . $fakeData);
    }

    private function registerAudioEvent(Closure $param)
    {
        $this->audioFileHandle = $param;
    }
}
