# Documentacao do `trunkController`

## Visao Geral

`libspech\Sip\trunkController` e o controlador principal de uma chamada SIP/RTP na `libspech`.
Ele concentra a sinalizacao SIP, a negociacao SDP, o controle de sockets UDP, a transmissao e recepcao RTP, callbacks de eventos de chamada, envio de DTMF, gravacao de audio e reproducao de arquivos WAV.

Arquivo documentado:

```text
plugins/Utils/libspech/trunkController.php
```

Classe:

```php
namespace libspech\Sip;

class trunkController
```

## Responsabilidades

- Criar e manter os sockets UDP de sinalizacao SIP e midia RTP.
- Enviar `OPTIONS`, `REGISTER`, `INVITE`, `ACK`, `CANCEL`, `BYE` e `REFER`.
- Tratar desafios Digest `401 Unauthorized` e `407 Proxy Authentication Required`.
- Montar e interpretar SDP para escolha de codec, payload type, porta e IP de audio.
- Receber pacotes SIP durante chamada e responder `OPTIONS`, `BYE`, `NOTIFY` e retransmissoes de `200 OK`.
- Iniciar o canal de midia RTP via `MediaChannel`.
- Enviar DTMF por RFC 2833/4733.
- Decodificar audio recebido para PCM quando necessario.
- Gravar buffers de audio e salvar WAV.
- Reproduzir WAV para o peer remoto com codificacao sob demanda.
- Expor callbacks para ringing, answer, hangup, failure, SDP, VAD, DTMF e PCM recebido.

## Dependencias Principais

O controller usa componentes internos da biblioteca e extensoes nativas:

| Dependencia | Uso |
|-------------|-----|
| `SocketMutable` | Socket UDP usado para SIP e RTP. |
| `Swoole\Coroutine` | Concorrencia nao bloqueante. |
| `Swoole\Timer` | Timeout programado de chamada. |
| `libspech\Network\network` | IP local e portas UDP livres. |
| `libspech\Packet\renderMessages` | Montagem de respostas e mensagens SIP auxiliares. |
| `libspech\Rtp\MediaChannel` | Canal de midia RTP. |
| `libspech\Rtp\rtpc` | Representacao de pacote RTP recebido. |
| `bcg729Channel` | Codec G.729. |
| `opusChannel` | Codec Opus. |
| Funcoes de audio | Encode/decode PCMA, PCMU, L16, resample, WAV e mixagem. |

## Criacao do Controller

Assinatura:

```php
public function __construct(
    mixed $username,
    mixed $password,
    mixed $host,
    mixed $port = 5060,
    mixed $domain = false,
    mixed $sipIpVersion = 4
)
```

Na construcao, o controller:

1. Inicializa credenciais SIP, `callerId`, callbacks e estado inicial.
2. Resolve o host informado somente na familia IP selecionada (`A` para IPv4, `AAAA` para IPv6).
3. Gera `CSeq`, `SSRC` e `Call-ID`.
4. Escolhe uma porta UDP livre para RTP.
5. Cria e faz bind do socket RTP.
6. Descobre separadamente o IP IPv4 de midia e o IP local da sinalizacao SIP.
7. Escolhe uma porta UDP livre para sinalizacao SIP.
8. Cria e faz bind do socket SIP.
9. Envia um `OPTIONS` inicial para testar conectividade.
10. Cria o `MediaChannel`.

Exemplo:

```php
use libspech\Sip\trunkController;

$phone = new trunkController(
    username: '1000',
    password: 'secret',
    host: 'sip.example.com',
    port: 5060
);
```

IPv4 e sempre o padrao. Para iniciar diretamente em IPv6, use o sexto argumento:

```php
$phone = new trunkController('1000', 'secret', 'sip.example.com', 5060, false, 6);
```

Tambem e possivel trocar somente o transporte SIP antes de `REGISTER`/`INVITE`:

```php
$phone->setSipIpVersion(6);
```

O setter recria o socket SIP e envia o `OPTIONS` inicial na nova familia. Ele nao altera o socket RTP nem o SDP de midia.

## Estado Interno Importante

| Propriedade | Finalidade |
|-------------|------------|
| `$username`, `$password`, `$host`, `$port` | Credenciais e destino SIP. |
| `$socket` | Socket UDP de sinalizacao SIP. |
| `$rtpSocket` | Socket UDP de audio RTP. |
| `$socketPortListen` | Porta local de SIP. |
| `$audioReceivePort` | Porta local de RTP. |
| `$audioRemoteIp`, `$audioRemotePort` | Destino RTP remoto obtido via SDP. |
| `$callId` | Identificador SIP da chamada. |
| `$csq` | Numero CSeq atual. |
| `$ssrc` | SSRC local usado no SDP/RTP. |
| `$callActive` | Indica chamada estabelecida. |
| `$error` | Indica falha operacional da chamada. |
| `$receiveBye` | Indica encerramento recebido ou solicitado. |
| `$closing` | Evita fechamento duplicado. |
| `$headers200` | Ultimo `200 OK` de INVITE, usado para BYE. |
| `$sdpReceived` | SDP remoto recebido. |
| `$mapLearn` | Mapa de payload types/codecs negociaveis. |
| `$bufferWriteSound` | Audio PCM acumulado por SSRC/frequencia/codec. |
| `$mediaChannel` | Canal RTP ativo. |

## Fluxo Basico de Uso

```php
\Swoole\Runtime::enableCoroutine();

\Swoole\Coroutine\run(function () {
    $phone = new trunkController('1000', 'secret', 'sip.example.com');

    $phone->mountLineCodecSDP('PCMA/8000');

    $phone->onRinging(function (trunkController $phone) {
        echo "Chamando...\n";
    });

    $phone->onAnswer(function (trunkController $phone) {
        echo "Atendida\n";
        $phone->receiveMedia();
    });

    $phone->onHangup(function (trunkController $phone) {
        echo "Encerrada\n";
    });

    $phone->call('5511999999999');
});
```

## Registro SIP

### `register(int $maxWait = 5): bool`

Envia `REGISTER` para registrar o ramal no servidor SIP.

Comportamento:

- Retorna `false` se usuario ou senha estiverem vazios.
- Envia um modelo de `REGISTER` criado por `modelRegister()`.
- Aguarda resposta ate `$maxWait`.
- Se receber `401`, monta `Authorization` ou `Proxy-Authorization`.
- Reenvia o `REGISTER` autenticado.
- Em `200 OK`, marca `$isRegistered = true`.

Exemplo:

```php
if (!$phone->register()) {
    throw new RuntimeException('Falha ao registrar SIP');
}
```

### `unRegister(): bool`

Remove o registro SIP enviando `REGISTER` com expiracao zero.

Comportamento:

- Usa `modelRegister(0)`.
- Ajusta `Contact: *` e `Expires: 0` quando precisa autenticar.
- Trata desafio Digest.
- Em `200 OK`, marca `$isRegistered = false`.

## Chamada Outbound

### `call(string $to, $maxRings = 120): bool`

Inicia uma chamada SIP usando `INVITE`.

Fluxo principal:

1. Monta o `INVITE` com SDP via `modelInvite()`.
2. Envia o pacote para `$host:$port`.
3. Aguarda respostas SIP.
4. Trata `401` ou `407` com ACK da transacao rejeitada e novo `INVITE` autenticado.
5. Dispara `onRinging()` para respostas `180`, `181`, `182` ou `183`.
6. Extrai destino RTP do SDP recebido.
7. Em `200 OK` de `INVITE`, envia `ACK`.
8. Marca `$callActive = true`.
9. Dispara `onAnswer()`.
10. Mantem loop de sinalizacao para tratar `OPTIONS`, `BYE`, `NOTIFY` e retransmissoes.

Eventos de falha:

- Timeout de toque.
- Falta de qualquer resposta inicial.
- Respostas finais `3xx`, `4xx`, `5xx` ou `6xx`, exceto desafios de autenticacao.
- `CANCEL` ou `BYE` antes da chamada ficar ativa.
- Socket fechado.

Exemplo:

```php
$phone->onFailed(function (string $reason) {
    echo "Falha: {$reason}\n";
});

$ok = $phone->call('5511999999999', 60);
```

## Chamada Ja Roteada

### `waitRoutedDialog(string $to, $maxRings = 120): bool`

Aguarda respostas de um dialogo cujo `INVITE` ja foi enviado por outro ponto do sistema.
E util quando a chamada foi roteada para este controller por outro socket.

O metodo:

- Filtra mensagens por `Call-ID`.
- Responde `OPTIONS`.
- Responde `CANCEL` e `BYE` com `200 OK`.
- Trata `1xx`, `2xx`, `487` e erros finais.
- Envia `ACK` para `200 OK` de `INVITE`.
- Atualiza SDP remoto e dispara callbacks de ringing/answer/hangup.

## Modelos SIP

Os metodos abaixo retornam arrays de sinalizacao compatveis com `sip::renderSolution()`.
Veja tambem `SIGNALING_ARRAYS.md`.

### `modelOptions(): array`

Cria um `OPTIONS` para teste de conectividade/capacidade.

### `modelRegister($expire = 120): array`

Cria o array SIP `REGISTER`.

Campos relevantes:

- `Via`
- `From`
- `To`
- `Call-ID`
- `CSeq`
- `Contact`
- `Expires`
- `Allow`
- `Content-Length: 0`

### `modelInvite(string $to, $prefix = "", $options = []): array`

Cria o array SIP `INVITE` com SDP.

Responsabilidades:

- Define `$calledNumber`.
- Garante usuario de origem.
- Monta as linhas de codec de `$mapLearn`.
- Cria o SDP local.
- Define payload principal (`$ptUse`) e payload DTMF (`$ptTelephoneEvent`).
- Preenche `inviteHeaders`.

Exemplo de SDP gerado:

```php
[
    "v" => ["0"],
    "o" => ["{$ssrc} 0 0 IN IP4 {$localIp}"],
    "s" => ["SPECHSHOP LIB"],
    "c" => ["IN IP4 {$localIp}"],
    "t" => ["0 0"],
    "m" => ["audio {$rtpPort} RTP/AVP 8 101"],
    "a" => [
        "ssrc:... cname:...",
        "rtpmap:8 PCMA/8000",
        "rtpmap:101 telephone-event/8000",
        "fmtp:101 0-15",
        "ptime:20",
        "sendrecv",
    ],
]
```

### `ackModel(array $headers): array`

Cria `ACK` para confirmar `200 OK` de `INVITE`.

Regras:

- Requer `Contact`, `CSeq`, `From`, `To` e `Call-ID`.
- Usa o mesmo numero de `CSeq`, trocando o metodo para `ACK`.
- Preserva `From`, `To` e `Call-ID`.
- Usa novo `Via` com branch novo.
- Inclui `Route` quando a resposta tem `Record-Route`.
- Retorna array vazio se faltarem headers obrigatorios.

### `getModelCancel($called = false): array`

Cria `CANCEL` para chamada ainda nao atendida.

### `modelBye(): array`

Cria `BYE` usando `$headers200`.
Depende de uma chamada que ja recebeu `200 OK`.

## Codec e SDP

### `mountLineCodecSDP(string $codec = 'PCMA/8000'): array`

Registra codec local e payload DTMF para o SDP.

Codecs estaticos:

| Codec | Payload |
|-------|---------|
| PCMU | `0` |
| PCMA | `8` |
| G729 | `18` |
| telephone-event | `101` quando disponivel |

Codecs dinamicos usam payloads entre `97` e `127`.
Para Opus, adiciona parametros `fmtp` relacionados a taxa, bitrate e FEC.

Exemplos:

```php
$phone->mountLineCodecSDP('PCMA/8000');
$phone->mountLineCodecSDP('PCMU/8000');
$phone->mountLineCodecSDP('G729/8000');
$phone->mountLineCodecSDP('opus/48000/2');
```

### `getSDPModelCodecs(array $sdpAttributes): array`

Interpreta linhas `a=` do SDP e retorna:

- `codecMediaLine`: payloads que entram na linha `m=`.
- `codecRtpMap`: linhas `rtpmap` e `fmtp` escolhidas.
- `preferredCodec`: codec principal, excluindo `telephone-event`.
- `dtmfCodec`: codec DTMF.
- `config`: parametros `fmtp` do codec principal.

### `parseArgumentRtpMap(string $line): array`

Extrai pares numericos de uma linha `fmtp`.

Exemplo:

```php
trunkController::parseArgumentRtpMap(
    'fmtp:97 maxplaybackrate=24000;maxaveragebitrate=64000'
);
```

## Midia RTP

### `receiveMedia(): void`

Inicia uma corrotina que configura e executa o `MediaChannel`.

Durante a inicializacao:

1. Evita iniciar duas leituras concorrentes usando `$socketInUse`.
2. Copia destino remoto de audio para `$remoteIp` e `$remotePort`.
3. Habilita VAD no `MediaChannel`, se solicitado.
4. Configura payload type e codec.
5. Interpreta `ssrc` do SDP remoto.
6. Registra o peer remoto como membro do canal RTP.
7. Define callback de recepcao RTP.
8. Define callback de envio continuo de audio de arquivo.
9. Inicia e bloqueia o `MediaChannel`.

No recebimento de RTP:

- Ignora payload vazio.
- Ignora `telephone-event` no fluxo de PCM.
- Decodifica PCMU, PCMA, G729, OPUS ou L16 para PCM.
- Atualiza espera por silencio, se ativa.
- Armazena audio quando gravacao esta habilitada.
- Executa callback de PCM recebido.
- Processa VAD, se ativo.

### `send2833(mixed $digit): void`

Envia DTMF pelo `MediaChannel`.

```php
$phone->send2833('1');
```

### `onReceivePcm(callable $param)`

Define callback para PCM decodificado.

Assinatura efetiva chamada pelo controller:

```php
function (
    string $pcmData,
    array $peer,
    trunkController $phone,
    string $packetCodecName,
    int $frequencyPacket
): void
```

Exemplo:

```php
$phone->onReceivePcm(function (
    string $pcm,
    array $peer,
    trunkController $phone,
    string $codec,
    int $rate
) {
    // Processar audio recebido.
});
```

## Reproducao de Audio

### `defineAudioFile(string $audioFile): void`

Prepara um WAV para envio RTP.

Comportamento:

- Valida e normaliza o arquivo com `secureAudioVoip()`.
- Le informacoes do WAV.
- Localiza o chunk `data`.
- Calcula chunks de 20 ms.
- Registra um evento de audio executado dentro do loop do `MediaChannel`.
- Codifica sob demanda para o codec negociado.
- Usa cache lazy por chunk codificado.
- Envia pacotes RTP para o peer remoto.

Codecs tratados:

- `PCMU`
- `PCMA`
- `G729`
- `OPUS`
- `L16`

### `autoReplayMedia(bool $option = true): void`

Controla se o arquivo de audio volta para o inicio quando chega ao fim.

```php
$phone->autoReplayMedia(false);
```

### `stopAudioFile(): void`

Substitui o handler de audio por um callback vazio, interrompendo a reproducao.

### `loadWavFile(string $wavFile): array`

Carrega um WAV e retorna PCM bruto e metadados:

```php
[
    'pcm' => string,
    'sampleRate' => int,
    'bitsPerSample' => int,
    'numChannels' => int,
    'chunkSize' => int,
]
```

## Gravacao e Buffers

### `enableAudioRecording(): void`

Habilita acumulacao de PCM recebido em `$bufferWriteSound`.

### `getBuffer(): string`

Mixa os canais gravados em um unico buffer PCM.

### `getBufferWriteSound(): array`

Retorna o buffer bruto agrupado por SSRC, frequencia e codec.

### `getBufferWriteSoundBySsrc(int $ssrc): array`

Retorna apenas o buffer de um SSRC.

### `saveBufferToWavFile(string $caminho, string $audioBuffer): void`

Grava um buffer PCM como WAV usando a frequencia e o numero de canais da chamada.

### `clearAudioBuffer(): void`

Limpa buffers de audio acumulados.

## VAD

### `enableVAD(): void`

Ativa Voice Activity Detection.

### `processVAD(string $pcmData, ...$extra): void`

Processa energia de um frame PCM e atualiza estado de voz ativa.

Comportamento:

- Calcula energia do PCM.
- Mantem historico de energia.
- Atualiza estimativa de ruido periodicamente.
- Calcula threshold adaptativo.
- Aplica hangover para evitar cortes secos.
- Atualiza metricas em `$audioMetrics`.
- Dispara `onVadChange()` em mudanca de estado ou relatorio periodico.

### `onVadChange(callable $callback): void`

Callback de VAD.

Assinatura:

```php
function (bool $isVoiceActive, float $energy, string $id): void
```

### `waitSilence($waitSilence = true, float $time = 1.0): bool`

Bloqueia a corrotina ate detectar silencio ou voz, conforme o parametro:

- `$waitSilence = true`: espera um periodo continuo de silencio.
- `$waitSilence = false`: espera atividade de voz.

## Callbacks de Sinalizacao

### `onFailed(Closure $callback): void`

Chamado quando a chamada falha.

Exemplos de motivo:

- Timeout.
- Socket fechado.
- Resposta final negativa.
- CANCEL recebido.

### `onAnswer(callable $callback): void`

Chamado quando a chamada recebe `200 OK` de `INVITE` e passa a estar ativa.

### `onRinging(Closure $param): void`

Chamado em respostas de progresso `180`, `181`, `182` ou `183`.

### `onHangup(callable $callback): void`

Chamado quando a chamada e encerrada por `BYE`, `NOTIFY`, erro ou fechamento.

### `onSdpReceived(Closure $param): void`

Chamado quando uma resposta SIP contem SDP e os parametros remotos de midia sao atualizados.

## DTMF

### `onKeyPress(callable $callback): void`

Define callback global para DTMF recebido.

### `registerDtmfCallback(string $dtmf, callable $callback): void`

Registra callback especifico por digito.

### `send2833(mixed $digit): void`

Envia DTMF para a chamada ativa.

## Encerramento de Chamada

### `cancel(): void`

Envia `CANCEL` para chamada pendente.
Marca `$cancelSent = true`.

### `bye(): void`

Envia `BYE` se a chamada ja recebeu `200 OK`.
Se ainda nao houver `$headers200`, chama `cancel()`.

### `close(): void`

Fecha o controller e libera recursos.

O metodo:

- E reentrante por causa de `$closing`.
- Marca erro, encerramento e chamada inativa.
- Limpa timers.
- Para o `MediaChannel`.
- Para proxy de midia, se ativo.
- Fecha sockets, exceto quando `$preserveSockets = true`.
- Remove callbacks.
- Limpa buffers e membros.

### `defineTimeout(int $time): void`

Agenda encerramento automatico da chamada apos `$time` segundos.
O timer tenta enviar `BYE` e depois chama `close()`.

## Transferencia

### `transfer(string $to): ?bool`

Envia `REFER` para transferir a chamada ativa para outro destino.

```php
$phone->transfer('5511888888888');
```

### `transferGroup(string $groupName, $retry = 0)`

Busca um grupo em `groups.json`, filtra agentes disponiveis usando `connections.json` e `calls.json`, e tenta transferir para agentes elegiveis.

Observacoes:

- Faz ate 3 tentativas recursivas.
- Exclui o proprio usuario.
- Exclui agentes ja presentes em chamadas.
- Depende de arquivos no `baseDir()` do plugin Utils.

## Utilitarios SIP

### `extractVia(string $line): array`

Extrai transporte, endereco, porta e parametros de uma linha `Via`.

### `extractURI($line): array`

Extrai usuario, host, porta, parametros do peer e parametros adicionais de uma URI SIP.

### `renderURI(array $uriData): string`

Monta URI SIP no formato:

```text
<sip:user@host:port>;tag=value
```

### `checkAuthHeaders(array $headers)`

Verifica se os headers de resposta exigem autenticacao.

Retornos possiveis:

- `"Proxy-Authorization"` para `Proxy-Authenticate`.
- `"Authorization"` para `WWW-Authenticate`.
- `false` quando nao ha desafio.

### `buildOkForRequest(array $request): string`

Cria uma resposta `200 OK` para uma request SIP recebida.

### `sendOkForRequest(array $request, array $peer): bool`

Envia `200 OK` ao peer que originou a request.

## Membros e Metadados

### `addMember(string $username): void`

Adiciona um usuario ao array `$members`.

### `removeMember(string $username): void`

Remove um usuario de `$members`.

### `isMember(string $username): bool`

Verifica se um usuario esta em `$members`.

### `saveGlobalInfo(string $key, $value): void`

Salva metadados livres em `$globalInfo`.

### `setCallId(string $callId): void`

Sobrescreve o `Call-ID`.

### `setCallerId(string $callerId): void`

Define o identificador de origem usado em headers e SDP.

### `resetTimeout(): void`

Atualiza `$timeoutCall` para `time()`.

### `getCid()`

Retorna o ID da corrotina capturado no construtor.

## Proxy de Midia

### `isProxyMediaActive(): bool`

Retorna se ha proxy de midia ativo.

### `getProxyId(): ?string`

Retorna o ID do proxy atual.

### `stopProxyMedia(): void`

Remove o proxy ativo via `rpcClient` e limpa o estado local.

## Sequencia Recomendada Para Chamada com Audio

```php
$phone = new trunkController($user, $pass, $host);

$phone->mountLineCodecSDP('PCMA/8000');
$phone->enableAudioRecording();
$phone->enableVAD();

$phone->onAnswer(function (trunkController $phone) {
    $phone->receiveMedia();
    $phone->defineAudioFile('/tmp/audio.wav');
});

$phone->onHangup(function (trunkController $phone) {
    $pcm = $phone->getBuffer();
    $phone->saveBufferToWavFile('/tmp/call.wav', $pcm);
    $phone->close();
});

$phone->call('5511999999999');
```

## Pontos de Atencao Para Manutencao

- `safeRecvfrom()` evita leituras simultaneas no socket SIP; quando retorna `null`, outro fluxo esta lendo.
- `call()` e `waitRoutedDialog()` contem loops longos de sinalizacao e devem preservar filtragem por `Call-ID`.
- `ACK` de challenge `401/407` nao e igual ao `ACK` de `200 OK`; o arquivo trata esses casos separadamente.
- `CANCEL` antes de `200 OK` pode cruzar com um `200 OK`; nesse caso o controller tenta enviar `BYE`.
- `receiveMedia()` depende de SDP ja recebido para configurar IP, porta, codec e payload type.
- `defineAudioFile()` usa codificacao lazy e cache por chunk; mudancas de codec ou canais invalidam o cache.
- `close()` limpa callbacks e buffers; nao chame esperando reutilizar o mesmo objeto para uma nova chamada completa.
- Muitas propriedades sao publicas por compatibilidade; alteracoes externas podem quebrar o estado da chamada.
- O controller assume ambiente com Swoole e extensoes/codecs disponiveis.

## Relacao Com Outros Documentos

- `SIGNALING_ARRAYS.md`: detalha a estrutura dos arrays SIP usados por `modelInvite()`, `modelRegister()`, `ackModel()`, `getModelCancel()` e `transfer()`.
- `README.md`: mostra o uso geral da biblioteca e o fluxo progressivo de chamada.
- `EXTRA_AUDIO_TOOLS.md`: complementa recursos relacionados a audio, quando aplicavel.
