# Monitor acústico incremental reutilizável

`libspech\audio\EarlyMediaAcousticMonitor` não conhece SIP, sockets ou bancos.
Recebe PCM16LE mono/8 kHz, em blocos incrementais inclusive com bytes ímpares.
Usa `AcousticStreamAnalyzer` nativo, sem reimplementar DSP. O runtime deve expor
`state.frame_signal`: sem essa telemetria o monitor fica inconclusivo/fail-open.

```php
$monitor = new \libspech\audio\EarlyMediaAcousticMonitor(minimumVoiceMs: 1000);
$monitor->push($pcm8kMono); // repetir conforme chegam os blocos
foreach ($monitor->takeEvents() as $event) {
    // Enfileirar somente metadados; não fazer I/O no receptor RTP.
}
$decision = $monitor->decision();
$metrics = $monitor->metrics();
$monitor->close();
```

A política separada `EarlyMediaVoicePolicy` exige, por padrão, 1.000 ms de quadros em que o
candidato nativo **e** a classificação estável sejam `voice_like`. Silêncio,
tons, bips e outras classes somam zero. Até 200 ms não vocais preservam o episódio;
mais de 200 ms zeram a evidência. Não se usa tempo de parede desde `speech.started`
nem se exige cadência de ring. Há somente uma decisão por monitor/tentativa.
O argumento `minimumVoiceMs` do monitor e da política aceita inteiros de 200 a
5.000 ms, em múltiplos de 20; valores inválidos lançam `InvalidArgumentException`.
A aplicação hospedeira carrega sua configuração uma vez e passa esse inteiro:
a libspech não lê `.env` nem conhece modos SIP.

A decisão é `classification=early_voice_activity`, `mailbox_proven=false`:
não identifica caixa postal, pessoa ou locução semanticamente. A fixture nativa
`music_mono_8000.wav` também pode satisfazer a política; validar em observe antes
de aplicar qualquer encerramento. Eventos ring/speech/signal nativos permanecem
como diagnóstico, nunca são isoladamente pedidos de encerramento.

## Tap RTP opcional

`libspech\Rtp\EarlyMediaAcousticTap` acopla o monitor a um `MediaChannel` existente:

```php
$tap = new \libspech\Rtp\EarlyMediaAcousticTap($monitor, $enqueueMetadata);
$channel->setEarlyMediaAcousticTap($tap);
// O membro já existente da operadora precisa ter metadado ['leg' => 'b'].
// Não adicionar outro membro, socket ou substituir onReceive/onPcm.
// No encerramento ou resposta INVITE final:
$tap->close('invite_final_received'); // preserva o motivo no resumo
$channel->setEarlyMediaAcousticTap(null);
```

O tap roda após o foreach de relay inalterado; recebe seu PCM se o relay já
decodificou. `receiveOnlyMode` também é suportado: o callback de trunk empresta o
PCM decodificado por `shareReceivedPcmForAcoustics()`. Aplicações que usam PCM direto
podem alimentar o monitor sem o tap, invalidando-o quando a origem for descontínua.

Codecs: PCMA/PCMU/G.729/OPUS. Conversão só quando é necessário chegar a mono/8 kHz;
nenhuma duplicação de decode de codec com estado no caminho compartilhado. O tap
privado possui seu próprio decoder apenas quando o relay não decodifica.

`AcousticRtpContinuity` acompanha SSRC, sequência, timestamp, formato/PT e chegada.
Duplicatas/atrasados não alimentam novamente. Ptime muda sem reset se o timestamp
acompanha os samples reais. Perda (mesmo pequena), SSRC/formato/timestamp novos ou
gap de chegada >200 ms invalidam histórico, conversão e decoder privado. Não há
padding de silêncio nem jitter buffer. Reinício com sequência antiga é descartado
de forma conservadora até avanço válido. Um decoder compartilhado G.729/OPUS com
continuidade não verificável encerra a análise fail-open, sem alterar o relay.

`metrics()` do tap inclui `shared_pcm_packets`, `analysis_decode_packets` e
contadores de perda/duplicação/atraso. O monitor informa `audio_ms`,
`qualified_voice_ms`, `processing_ns`, `continuity_epoch`, bytes pendentes e estado.
Eventos têm tempo de áudio e `monotonic_ns`; diagnósticos repetidos são limitados.

`analysis.stopped` é emitido uma única vez, inclusive sem decisão, com
`reason`, `minimum_voice_ms`, `maximum_qualified_voice_ms`, `speech_episodes`,
`frame_counts`, `rtp_discontinuities`, `discontinuity_reasons`, `decision_emitted`
e `failure_reason`. Esses campos também estão em `metrics()` (`stop_reason`
expõe o motivo fora do evento). O máximo e os contadores sobrevivem aos resets
da evidência; episódios contam o primeiro quadro vocal válido após evidência
zerada, com a mesma tolerância de pausa de 200 ms. `frame_counts` conta o candidato
nativo `state.frame_signal`, inclusive antes do debounce; não representa duração
qualificada. São apenas incrementos sobre o estado já obtido, sem nova análise.
`decision_emitted=true` significa decisão produzida pelo monitor; a entrega e o
resultado SIP são responsabilidade do integrador. Erros encerram com
`reason=fail_open`, acompanhados do detalhe em `failure_reason`.

Desabilitado: não chame `setEarlyMediaAcousticTap()` nem construa o monitor.
MediaChannel executa apenas um guard falso adicional no relay normal, sem DSP,
conversão, buffers ou coroutine acústica. Callbacks/membros/DTMF/VAD/gravação e
receiveOnly conservam seus caminhos. `close()` desanexa o tap e libera callbacks e
recursos nativos. Falhas são fail-open; o aplicativo proprietário da transação SIP
é quem pode decidir e enviar CANCEL/BYE, nunca este componente.

Testes e benchmarks de integração estão no SipSwoole: `tests/EarlyMedia*Test.php`,
`bench/early-acoustic-rtp.php`, `bench/early-acoustic-disabled-paired.php` e
`docs/early-media-acoustic-validation.md`. Não foi feita chamada externa real.
