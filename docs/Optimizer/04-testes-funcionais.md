# 04 — Testes funcionais

Comandos na raiz da branch:

```sh
git archive 0ccb9a3 | tar -x -C /tmp --one-top-level=libspech-optimizer-baseline
touch /tmp/libspech-optimizer-baseline/.env .env
python3 tests/mediachannel/run.py "$PHP" /tmp/libspech-optimizer-baseline /tmp/before.json
python3 tests/mediachannel/run.py "$PHP" "$PWD" /tmp/after.json
```

O primeiro comando de testes **deve falhar**, mantendo o exit original e logs. JSONs completos em `evidence/before.json`, `after.json`, `release-runtime.json`. O runner executa testes preexistentes a partir de cada raiz, inclusive quando usam `__DIR__`; não testa acidentalmente a candidata como baseline.

| Contrato | Antes | Depois |
|---|---|---|
| END único, wrap de sequência, retransmissão idêntica, fora de ordem | Falha | Passa |
| Callback ausente em progress; END seguido de progress atrasado; forwarding de cada pacote | Falha no conjunto | Passa |
| Timeout ocioso fallback | 0 callbacks em .45 s para timeout=0 | 1 callback |
| Datagram curto sem métricas | 1 membro/callback | 0 |
| Payload PCMA/PCMU 320 bytes, destino20 ms | [320] bytes | [160,160] bytes |
| CSRC/extensão/padding truncados, inválidos e válidos | Admite curto | Matriz passa |
| RTP vazio keepalive, RTP com CSRC/extensão, padding removido no callback | Sem garantia de admissão | Passa em ambos seletores de recepção |
| Buffers17B + frame completo + restante; ptime10/20/30/40 | Não preserva packetização | Tamanho, sequência, timestamp e PCM decodificado exatos |
| Encoded relay com duração real correta e ptime nominal divergente | Recode desnecessário | Byte exato, incluindo PCMU negative zero |
| Timeout1s, observação .6s sem tráfego | Não expira | Uma leitura pendente, sem polling; expira em1s |
| RTCP, ordem1/3/2/4, relay, rebind, remoção pública, close repetido | Passa | Passa |
| Matriz PCMA/PCMU/G729/GSM/Opus→PCMA, acústica e timer teardown | Passa | Passa |

Regressões permanentes: parser (773 casos), conversor PCM streaming, playback/resampling, member ptime e trunk recording. A suite original de member ptime falha na baseline no mock cancel. A correção de fixture é explicada em03; o erro original fica em `evidence/test_media_channel_member_ptime.php.txt`. Resultados não são apresentados como aprovação da fixture original.

Paridade acústica: `acoustic-parity.php`, tons425Hz, entradas320/137/960B, métricas/eventos iguais antes/depois após retirar relógios. Não equivale a corpus de voz/música nem teste de qualidade perceptual.

Limitações explícitas: não é suite completa de todos os scripts do repositório (há demos e programas dependentes de serviços/arquivos externos). Sem chamada SIP real, carga bidirecional prolongada, Opus/G729 todos→todos, restart ou soak de FDs. Runtime local modificado cobre recepção nativa; runtime publicado da CI pode selecionar fallback conforme capacidade. Nenhum SKIP é contado como aprovado.

Capacidades efetivamente observadas nos dois binários: Swoole Buffer e recvfromInto presentes (`evidence/capabilities.json`). Os seletores false/true do teste edges exercitam fallback e seleção nativa, além da comparação do parser nativo. A identificação de RTCP continua a preexistente por versão/tipo; esta etapa não implementa validador de RTCP composto nem muda a política de mux.
