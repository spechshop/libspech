<?php

namespace libspech\Rtp;

use bcg729Channel;
use Closure;
use libspech\Cache\cache;
use libspech\Cli\cli;

use libspech\Network\network;
use opusChannel;
use Swoole\Coroutine;
use Swoole\Coroutine\Socket;
use Throwable;
use function libspech\Sip\monoToStereo;
use function libspech\Sip\volumeAverage;


class MediaChannel
{
    public bool $active = true;

    public int $connectTimeout = 10;


    // pcm 8khz silence
    private string $syl = '';
    public bool $debugEnabled = false;
    private array $settings = [];
    private $lastVoiceActivity = 0;

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
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
     *     'ssrc' => int,
     *     'timestamp' => int,
     *     'config' => array,
     *     'opus' => ?opusChannel,
     *     'frequency' => int,
     *     'rtpChannel' => rtpChannel
     * ]
     *
     * @var array<string, array{address: string, port: int, codec: string, pt: int, ssrc: int, timestamp: int, config: array, LPCM_MONO: ?LPCM, LPCM_STEREO: ?LPCM, opus: ?opusChannel, frequency: int, rtpChannel: rtpChannel}>
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
    public array $audioMetrics = [
        'total_packets' => 0,
        'lost_packets' => 0,
        'avg_energy' => 0.0,
        'voice_time' => 0.0,
        'silence_time' => 0.0,
    ];
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
    public Socket $eventSock;
    public int $listenPort = 0;

    public function __construct(Socket|\SocketMutable &$socket, string $callId)
    {

        $this->settings = [
            'sendSilenceProbeToMembers' => true,
        ];
        $this->socket = $socket;
        $this->callId = $callId;
        $this->syl = str_repeat("\0\0", 160);


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

        if (!empty($this->ptCodecsFrequency[$this->ptCodecs[$pt]])) {
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


            //cli::pcl("DTMF: {$event} {$volume} {$duration} {$end} {$targetId}", 'bold_green');


            $frequencyMember = $this->ptCodecsFrequency[$info['codec']] ?? 8000;

            // Encontrar o PT correto do telephone-event para este destino
            $telephoneEventPt = $this->findTelephoneEventPt($frequencyMember);

            // Configurar o PT do telephone-event no canal de destino
            $this->members[$targetId]['rtpChannel']->setNewPtDTMF($telephoneEventPt);

            // Detectar se é o primeiro pacote do evento (marker bit)
            $isFirstPacket = ($rtpc->marker === 1);


            // Construir e enviar pacote DTMF preservando timestamp original
            $outPacket = $this->members[$targetId]['rtpChannel']->buildDtmfForwardPacket(
                $rtpc->payloadRaw,
                $rtpc->timestamp,
                $isFirstPacket
            );

            $this->socket->sendto($info['address'], $info['port'], $outPacket);
        }
    }

    private function debugRtcpPacket(string $packet, array $peer): void
    {
        $len = strlen($packet);

        if ($len < 4) {
            cli::pcl("RTCP inválido len={$len}", 'red');
            return;
        }

        $offset = 0;
        $index = 0;

        while (($offset + 4) <= $len) {
            $b0 = ord($packet[$offset]);
            $pt = ord($packet[$offset + 1]);

            $version = ($b0 >> 6) & 0x03;
            $padding = ($b0 >> 5) & 0x01;
            $count = $b0 & 0x1F;

            $lengthWords = unpack('n', substr($packet, $offset + 2, 2))[1];

            // length = número de palavras de 32 bits menos 1
            $blockLen = ($lengthWords + 1) * 4;

            if ($blockLen <= 0 || ($offset + $blockLen) > $len) {
                cli::pcl(
                    "RTCP bloco inválido from {$peer['address']}:{$peer['port']} " .
                    "offset={$offset} pt={$pt} blockLen={$blockLen} total={$len}",
                    'red'
                );
                return;
            }

            $name = match ($pt) {
                200 => 'SR',
                201 => 'RR',
                202 => 'SDES',
                203 => 'BYE',
                204 => 'APP',
                205 => 'RTPFB',
                206 => 'PSFB',
                207 => 'XR',
                default => 'UNKNOWN',
            };

            $msg = "RTCP[$index] from {$peer['address']}:{$peer['port']} " .
                "type={$pt}({$name}) " .
                "v={$version} p={$padding} count={$count} " .
                "words={$lengthWords} bytes={$blockLen} offset={$offset}";

            if (($pt === 200 || $pt === 201) && $blockLen >= 8) {
                $ssrc = unpack('N', substr($packet, $offset + 4, 4))[1];
                $msg .= " ssrc={$ssrc}";
            }

            if ($pt === 200 && $blockLen >= 28) {
                $senderSsrc = unpack('N', substr($packet, $offset + 4, 4))[1];
                $ntpMsw = unpack('N', substr($packet, $offset + 8, 4))[1];
                $ntpLsw = unpack('N', substr($packet, $offset + 12, 4))[1];
                $rtpTs = unpack('N', substr($packet, $offset + 16, 4))[1];
                $packetCount = unpack('N', substr($packet, $offset + 20, 4))[1];
                $octetCount = unpack('N', substr($packet, $offset + 24, 4))[1];

                $msg .= " senderSsrc={$senderSsrc}" .
                    " ntp={$ntpMsw}.{$ntpLsw}" .
                    " rtpTs={$rtpTs}" .
                    " packets={$packetCount}" .
                    " octets={$octetCount}";
            }

            cli::pcl($msg, 'yellow');

            $offset += $blockLen;
            $index++;
        }

        if ($offset !== $len) {
            cli::pcl("RTCP trailing bytes: " . ($len - $offset), 'yellow');
        }
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
                    cli::pcl("MediaChannel: Desligado", 'bold_red');
                    return;
                }
                $peer = ['address' => '0.0.0.0', 'port' => 0];
                $packet = $this->socket->recvfrom($peer, 0.2);
                $currentTime = microtime(true);




                if (!$packet) {
                    $now = $currentTime;
                    $elapsed = $now - $lastPacketTime;
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

                    // Agora sim: timeout real da chamada.
                    cli::pcl(
                        "TIMEOUT: no packets received for {$elapsed} seconds, exceed: {$this->connectTimeout}",
                        'bold_red'
                    );


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


                $idFrom = "{$peer['address']}:{$peer['port']}";
                $this->audioMetrics['total_packets']++;
                if ($this->isRtcpPacket($packet)) {
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


                if (!array_key_exists($ssrc, $this->rtpChans)) {
                    $this->rtpChans[$ssrc] = new rtpChannel($rtpc->getCodec(), $this->ptCodecsFrequency[$codec] ?? 8000, 20, $ssrc);
                    $this->rtpChans[$ssrc]->sequenceNumber = $rtpc->sequence++;
                    $this->rtpChans[$ssrc]->timestamp = $rtpc->timestamp;
                    $this->rtpChans[$ssrc]->bcg729Channel = new bcg729Channel();
                }

                if (!$this->isMember($idFrom)) {
                    $this->addMember([
                        'address' => $peer['address'],
                        'port' => $peer['port'],
                        'codec' => $codec,
                        'pt' => $pt,
                        'ssrc' => $ssrcOrigin,
                        'ssrcReceived' => $rtpc->ssrc,
                        'timestamp' => $rtpc->timestamp,
                        'config' => $this->options['config'] ?? [],
                        'opus' => $this->members[$idFrom]['opus'] ?? null,
                        'frequency' => $this->resolveFrequencyFromPt($rtpc->getCodec()) ?? 8000,
                    ]);
                }


                $pcmData = false;





                if ($this->onReceiveCallable) {
                    go(function () use ($rtpc, $peer, $ssrc) {
                        call_user_func($this->onReceiveCallable, $rtpc, $peer, $this, $this->rtpChans[$ssrc]);
                    });
                }


                if (!array_key_exists($rtpc->getCodec(), $this->ptCodecs)) {
                    $member = $this->members[$idFrom] ?? null;
                    if ($member) {
                        $this->ptCodecs[$rtpc->getCodec()] = $member['codec'] ?? $this->defaultCodec;
                    }
                }

                $pt = $rtpc->getCodec();


                if (strtolower($codec) === 'telephone-event') {
                    //cli::pcl("$idFrom TELEPHONE-EVENT  " . time(), 'yellow');
                    $this->forwardDtmfToMembers($rtpc, $peer, $idFrom);


                    $this->processDtmf($rtpc, $peer, function () {

                    });

                    continue;
                }




                try {
                    $pcmData = match (strtoupper($codec)) {
                        'G729' => $this->rtpChans[$ssrc]->bcg729Channel->decode($rtpc->payloadRaw),
                        'PCMU' => decodePcmuToPcm($rtpc->payloadRaw),
                        'PCMA' => decodePcmaToPcm($rtpc->payloadRaw),
                        'OPUS' => $this->members[$idFrom]['opus']->decode($rtpc->payloadRaw),
                        'L16' => decodeL16ToPcm($rtpc->payloadRaw),
                        default => false
                    };
                } catch (Throwable $e) {
                    continue;
                }
                if ($pcmData === false) continue;
                if ($this->vadEnabled) {
                    $volume = volumeAverage($pcmData, $this->members[$idFrom]['frequency'] ?? 8000);
                    if ($volume > 1) {
                        $this->lastVoiceActivity = $currentTime;
                    }
                    $diffInMs = $currentTime - $this->lastVoiceActivity;
                    $diff = round($diffInMs / 1000, 2);
                    if ($diff >= $this->vadTimeoutSeconds) {
                        cli::pcl("VAD: $idFrom desativado por timeout de {$this->vadTimeoutSeconds} segundos após $diff segundos", 'red');
                         $this->close();
                         return;
                    }
                }







                foreach ($this->members as $targetId => $info) {
                    if ($targetId === $idFrom) continue;


                    $freqOriginPacket = (int)($this->members[$idFrom]['frequency'] ?? $this->ptCodecsFrequency[$info['codec']] ?? 8000);




                    //   else var_dump($rtpc);


                    //  }


                    $encode = null;
                    $frequencyMember = $info['frequency'] ?? $this->ptCodecsFrequency[$info['codec']] ?? 8000;


                    switch (strtoupper($info['codec'])) {
                        case 'PCMU':
                            if (strtoupper($codec) === strtoupper($info['codec'])) {
                                $encode = $rtpc->payloadRaw;
                                break;
                            }
                            if ($freqOriginPacket !== 8000) $pcmData = resampler($pcmData, $freqOriginPacket, 8000);
                            $encode = encodePcmToPcmu($pcmData);
                            break;
                        case 'PCMA':
                            if (strtoupper($codec) === strtoupper($info['codec'])) {
                                $encode = $rtpc->payloadRaw;
                                break;
                            }
                            if ($freqOriginPacket !== 8000) $pcmData = resampler($pcmData, $freqOriginPacket, 8000);
                            $encode = encodePcmToPcma($pcmData);
                            break;
                        case 'G729':
                            if (strtoupper($codec) === strtoupper($info['codec'])) {
                                $encode = $rtpc->payloadRaw;
                                break;
                            }
                            if (!array_key_exists('bcg729Channel', $this->members[$targetId]))
                                $this->members[$targetId]['bcg729Channel'] = new bcg729Channel();


                            if ($codec == 'G729') {
                                $encode = $rtpc->payloadRaw;
                            } else {
                                $encode = $this->members[$idFrom]['rtpChannel']->bcg729Channel->encode($pcmData);
                            }
                            break;
                        case 'OPUS':

                            try {

                                $isStereo = $this->members[$targetId]['config']['stereo'] ?? false;
                                if ($isStereo) $pcmData = resample($pcmData, $freqOriginPacket, $info['frequency'], [
                                    'input_channels' => $this->ptCodecsChannels[$rtpc->getCodec()] ?? 1,
                                    'output_channels' => $this->ptCodecsChannels[$info['pt']] ?? 1,
                                ]);
                                else $pcmData = resampler($pcmData, $freqOriginPacket, $info['frequency']);

                                $encode = $this->members[$targetId]['opus']->encode($pcmData);


                            } catch (\Throwable $e) {
                                cli::pcl("OPUS ERROR: " . $e->getMessage(), 'red');
                                // Reset and reconfigure Opus encoder
                                $this->members[$targetId]['opus']->reset();
                                $this->members[$targetId]['opus']->setVBR(true);
                                $this->members[$targetId]['opus']->setComplexity(1);
                                $this->members[$targetId]['opus']->setDTX(false); // serve para>
                                $this->members[$targetId]['opus']->setSignalVoice(true);


                                $pcmData = resampler($pcmData, $freqOriginPacket, 48000);

                                try {
                                    $encode = $this->members[$targetId]['opus']->encode($pcmData, 48000);
                                    $this->members[$targetId]['opus']->reset();
                                } catch (\Throwable $e) {
                                    // Reset and reconfigure Opus encoder
                                    $this->members[$targetId]['opus']->reset();
                                }
                            }


                            break;
                        case 'L16':

                            if ($this->ptCodecsChannels[$info['pt']] > 1) {
                                // Converte mono para estéreo e resample para a frequência do destino
                                //$encode = resample($pcmData, $frequencyPacket, $info['frequency'], [
                                //    'input_channels' => 1,
                                //    'output_channels' => 2,
                                //    'work_channels' => 1,
                                //]);
                                $encode = monoToStereo($pcmData);
                                $encode = resampler($encode, $freqOriginPacket, $info['frequency'], 1);
                            } else {
                                $encode = resampler($pcmData, $freqOriginPacket, $info['frequency'], 1);
                            }
                            break;
                        default:
                            $encode = $rtpc->payloadRaw;
                            break;
                    }

                    // Calcular incremento de timestamp baseado na frequência e tipo de payload


                    $newPacket = $this->members[$targetId]['rtpChannel']->buildAudioPacket($encode);


                    $this->socket->sendto($info['address'], $info['port'], $newPacket);

                    if ($pcmData !== false) {
                        if ($this->vadEnabled) {
                            $this->processVAD($pcmData, $idFrom);
                        }
                    }
                }
                if ($this->debugEnabled) {
                    if (empty($lastDebug)) $lastDebug = microtime(true);
                    if (microtime(true) - $lastDebug >= 0.160) {



                        $timeMS = round((microtime(true) - $lastPacketTime) * 1000, 2);
                        cli::pcl("$this->callId MediaChannel: " . $timeMS . "ms com ".count($this->members). " membros",
                            !empty($packet) ? 'bold_green' : 'bold_red'
                        );
                        $lastDebug = microtime(true);

                    }
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


        if ($peer['config'] ?? ['stereo'] ?? false) {
            $channels = $peer['config']['stereo'] ?? false;
        } else {
            $channels = $this->options['stereo'] ?? false;
        }
        if ($channels === false) $nc = 1;
        else $nc = 2;


        if (empty($peer['config']['stereo'])) $nc = 1;
        $peer['opus'] = new opusChannel(48000, $nc);

        // $peer['LPCM_MONO'] = new LPCM(1, 16);

        // $peer['LPCM_STEREO'] = new LPCM(2, 16);


        $rate = $peer['frequency'];
        $id = "{$peer['address']}:{$peer['port']}";

        if (!empty($peer['config'])) {
            if (!empty($peer['config'][(int)$peer['pt']])) {
                if (!empty($peer['config']['maxaveragebitrate'])) {
                    $rate = 'Max. Average Bitrate: ' . $peer['config']['maxaveragebitrate'] . ' ';
                    $peer['opus']->setBitrate((int)$peer['config']['maxaveragebitrate']);
                } elseif (!empty($peer['config']['maxplaybackrate'])) {
                    $rate = 'Max. Playback Rate: ' . $peer['config']['maxplaybackrate'] . ' ';
                    $peer['opus']->setBitrate((int)$peer['config']['maxplaybackrate']);
                }


                $config = $peer['config'];
                if (!empty($config['userdtx'])) $peer['opus']->setDTX(true);
                if (!empty($config['cbr'])) $peer['opus']->setVBR(true);
                $peer['opus']->setComplexity(2);
                $peer['opus']->setSignalVoice(true);
                $peer['opus']->setDTX(true);

                $peer['opus']->setVBR(true);


            }
        }
        $peer['opus']->setBitrate($peer['config']['maxplaybackrate'] ?? 24000);


        $peer['rtpChannel'] = new rtpChannel((int)$peer['pt'], $peer['frequency'], 20, $this->generateDeterministicSsrc($id));
        $peer['rtpChannel']->setSsrc($this->generateDeterministicSsrc($id));
        $this->ptCodecsChannels[$peer['pt']] = $nc;
        if (!array_key_exists('channels', $peer)) $peer['channels'] = $nc;
        $this->members[$id] = $peer;
    }

    private function processVAD(string $pcmData, ...$extra): void
    {
        if (!$this->vadEnabled) {
            return;
        }
        $energy = volumeAverage($pcmData);
        $this->audioMetrics['avg_energy'] = $this->audioMetrics['avg_energy'] * 0.9 + $energy * 0.1;
        $wasActive = $this->isVoiceActive;
        $idFrom = $extra[0] ?? $this->callId;
        if ($energy > $this->vadRegistrationThreshold) {
            if (!isset($this->registeredIds[$idFrom])) {
                $this->registeredIds[$idFrom] = true;
            }
            $this->lastVadActivity[$idFrom] = microtime(true);
        }
        if ($energy > $this->vadThreshold) {
            $this->isVoiceActive = true;
            $this->vadCurrentHangover = $this->vadHangoverFrames;
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
        if ($this->isVoiceActive) {
            $this->audioMetrics['voice_time'] += 0.02;
        } else {
            $this->audioMetrics['silence_time'] += 0.02;
        }
    }

    public function getAudioMetrics()
    {
        return $this->audioMetrics;
    }

    public bool $dtmfInUse = false;

    public function send2833(string $digit): void
    {
        try {
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
            $this->dtmfInUse = true;

            // MicroSIP usa PJSIP; o default do PJSIP é:
            // - volume = 10
            // - duração total = 1600 timestamps (200ms em telephone-event/8000)
            // - retransmissão do pacote final com E-bit = 3 vezes
            // - primeiro pacote com marker bit = 1
            // - timestamp do evento fixo durante todo o dígito
            $volume = 10;
            $endRetransmits = 3;
            $eventClockRate = 8000;
            $ptimeMs = 20;
            $durationMs = 200;

            $stepSamples = (int)round(($eventClockRate * $ptimeMs) / 1000);
            if ($stepSamples <= 0) {
                $stepSamples = 160;
            }

            $finalDurationSamples = (int)round(($eventClockRate * $durationMs) / 1000);
            if ($finalDurationSamples <= 0) {
                $finalDurationSamples = 1600;
            }

            $steps = (int)ceil($finalDurationSamples / $stepSamples);
            if ($steps < 1) {
                $steps = 1;
            }

            foreach ($this->members as $key => $member) {
                $ip = $member['address'] ?? null;
                $port = $member['port'] ?? null;
                if (empty($ip) || empty($port)) {
                    continue;
                }

                $rtpChannel = $member['rtpChannel'] ?? null;
                if (!$rtpChannel instanceof rtpChannel) {
                    continue;
                }

                $ptTelephoneEvent = $this->findTelephoneEventPt((int)($member['frequency'] ?? 8000));

                // Timestamp do evento deve ficar constante em todos os pacotes do mesmo dígito
                $eventTs = (int)$rtpChannel->timestamp;
                $ssrc = (int)$rtpChannel->ssrc;

                // Pacotes de progresso do evento
                for ($i = 1; $i <= $steps; $i++) {
                    $duration = $i * $stepSamples;
                    if ($duration > $finalDurationSamples) {
                        $duration = $finalDurationSamples;
                    }

                    $isFirst = ($i === 1);
                    $isLast = ($duration >= $finalDurationSamples);

                    // Byte 2 do payload:
                    // bit 7 = E (não setar aqui; os pacotes End são enviados separadamente)
                    // bits 0..5 = volume
                    $eVol = $volume & 0x3F;

                    $payload = pack(
                        'CCn',
                        $event,
                        $eVol,
                        $duration
                    );

                    // Marker bit somente no primeiro pacote
                    $b1 = 0x80;
                    $b2 = ($isFirst ? 0x80 : 0x00) | ($ptTelephoneEvent & 0x7F);

                    $hdr = pack(
                        'CCnNN',
                        $b1,
                        $b2,
                        $rtpChannel->sequenceNumber++ & 0xFFFF,
                        $eventTs & 0xFFFFFFFF,
                        $ssrc & 0xFFFFFFFF
                    );

                    $this->socket->sendto($ip, $port, $hdr . $payload);

                    // Dorme entre os pacotes, exceto depois do último "progresso"
                    if (!$isLast) {
                        Coroutine::sleep($ptimeMs / 1000);
                    }
                }

                // Retransmite o último pacote com E-bit 3 vezes
                $payloadEnd = pack(
                    'CCn',
                    $event,
                    0x80 | ($volume & 0x3F),
                    $finalDurationSamples
                );

                for ($r = 0; $r < $endRetransmits; $r++) {
                    $hdr = pack(
                        'CCnNN',
                        0x80,
                        $ptTelephoneEvent & 0x7F,
                        $rtpChannel->sequenceNumber++ & 0xFFFF,
                        $eventTs & 0xFFFFFFFF,
                        $ssrc & 0xFFFFFFFF
                    );

                    $this->socket->sendto($ip, $port, $hdr . $payloadEnd);

                    if ($r < $endRetransmits - 1) {
                        Coroutine::sleep($ptimeMs / 1000);
                    }
                }

                // Mantém a timeline contínua
                $rtpChannel->timestamp = ($eventTs + $finalDurationSamples) & 0xFFFFFFFF;
                $this->dtmfInUse = false;
            }
        } catch (\Throwable $e) {
            $this->dtmfInUse = false;
            return;
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
            if (empty($member['address']) || empty($member['port'])) {
                continue;
            }

            if (!isset($member['rtpChannel'])) {
                continue;
            }

            try {
                $payload = $this->makeSilencePayloadForMember($member);
                if ($payload === null) {
                    continue;
                }
                $packet = $this->members[$idMember]['rtpChannel']->buildAudioPacket($payload);
                $this->socket->sendto($member['address'], $member['port'], $packet);
            } catch (\Throwable $e) {
            }
        }
    }

    private function makeSilencePayloadForMember(array $member): ?string
    {
        $codec = strtoupper($member['codec'] ?? 'PCMA');
        $frequency = (int)($member['frequency'] ?? 8000);

        switch ($codec) {
            case 'PCMA':
                $samples = (int)(($frequency / 1000) * 20);
                return str_repeat("\xD5", $samples);

            case 'PCMU':
                $samples = (int)(($frequency / 1000) * 20);
                return str_repeat("\xFF", $samples);

            case 'L16':
            case 'PCM':
                $channels = $member['channels'] ?? 1;
                $samples = (int)(($frequency / 1000) * 20) * $channels;
                return str_repeat("\x00\x00", $samples);

            case 'OPUS':
                if (!isset($member['opus'])) {
                    return null;
                }
                // 960 samples por canal = 20ms em 48000Hz
                $pcm = str_repeat("\x00\x00", 960);
                return $member['opus']->encode($pcm);

            case 'G729':
                if (!isset($member['bcg729Channel'])) {
                    return null;
                }
                // Dois frames de 10ms (80 samples @ 8kHz cada)
                $pcm10ms = str_repeat("\x00\x00", 80);
                return $member['bcg729Channel']->encode($pcm10ms)
                    . $member['bcg729Channel']->encode($pcm10ms);

            default:
                return null;
        }
    }

    public function close(): void
    {
        $this->active = false;
        try {
            $this->socket->close();
            if (!$this->socket->isClosed()) {
                $this->socket->close();
            }
        } catch (\Throwable $e) {
        }

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

        // Limpa os membros
        foreach ($this->members as $id => $member) {
            try {
                if (isset($member['opus']) && is_a($member['opus'], opusChannel::class)) {
                    if (method_exists($member['opus'], 'destroy')) {
                        $member['opus']->destroy();
                    }
                }
                if (isset($member['rtpChannel'])) {
                    unset($member['rtpChannel']);
                }
            } catch (\Throwable $e) {
            }
        }

        // Limpa arrays
        $this->members = [];
        $this->rtpChans = [];
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
                if (strtolower($codecName) === 'telephone-event') {
                    // Verificar se existe a chave composta para esta frequência
                    if (isset($this->ptCodecsFrequency['telephone-event_' . $frequency])) {
                        return $pt;
                    }
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


            // Ajustar timestamps dos membros para compensar duração do DTMF
            // RFC 4733: duration está em unidades de timestamp (samples)
            foreach ($this->members as $idTarget => $info) {
                if ($idTarget == "{$peer['address']}:{$peer['port']}") continue;
                if (array_key_exists('ssrc', $info) && $info['ssrc'] == $rtpc->ssrc) {
                    continue;
                }

                // Incrementar timestamp baseado na duração real do evento
                // Usar duration do pacote ao invés de valor fixo
                if (isset($this->members[$idTarget]['timestamp'])) {
                    $this->members[$idTarget]['timestamp'] += $duration;
                }
            }

            // Disparar callback de DTMF
            $callback = $this->onDtmfCallable;
            if (is_callable($callback)) {
                go($callback, $digit, $peer, $event, $this);
            }

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