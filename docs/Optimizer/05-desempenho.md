# 05 — Desempenho

Baseline `0ccb9a3` contra código de produção `d71986c` (MediaChannel/rtpc idênticos após commits documentais/CI). PHP local8.5.12 ZTS, Swoole6.3.0RC1, psampler0.7.0; SHA256 e binário em `evidence/runtime.json`. O runtime publicado de CI é diferente e não foi misturado nas medições.

## Método

Cinco repetições intercaladas AB/BA, mesmas entradas, sockets UDP loopback efêmeros,50 entradas/run, espaçamento20ms, espera40ms no final. Métricas RTP habilitadas, acústica integral ligada salvo cenário explícito noacoustic. one=um membro; same=dois PCMA; transcode=PCMA→PCMU; dtmf=dois PCMA com3 END; ptime10/40=entrada160B/20ms e destino10/40ms. CPU getrusage cobre gerador, receptor, pacer e drainer no mesmo processo. Não isola custo exclusivo de MediaChannel.

Comandos:

```sh
python3 tests/mediachannel/measure.py "$PHP" /tmp/libspech-optimizer-baseline "$PWD" docs/Optimizer/evidence
python3 tests/mediachannel/counters.py "$PHP" /tmp/libspech-optimizer-baseline "$PWD" docs/Optimizer/evidence/perf
```

Executar serialmente, sem outro benchmark concorrente. Dados brutos: `bench.jsonl`,70 registros,5 por variante/cenário; `bench-summary.json` inclui valores individuais, mínimo, máximo e desvio amostral. A série final substitui o ensaio preliminar: a revisão simplificou o predicado de duração, e todo o comparativo foi repetido sobre o código final. Não houve alteração do gerador entre baseline/candidata.

## CPU por entrada RTP

Unidade µs/entrada; mediana [mínimo,máximo]; DP=desvio padrão amostral. Delta positivo é aumento de CPU, não ganho.

| Cenário | Antes | Depois | DP antes/depois | Delta mediana |
|---|---:|---:|---:|---:|
| dtmf | 448.80 [333.44,473.66] | 474.02 [426.44,485.06] | 56.38/23.63 | +5.62% |
| noacoustic | 368.04 [222.98,406.08] | 380.60 [308.64,403.42] | 78.58/36.79 | +3.41% |
| one | 402.58 [341.98,424.08] | 419.30 [356.24,435.58] | 34.71/31.61 | +4.15% |
| ptime10 | 387.60 [125.78,461.12] | 620.42 [385.58,742.96] | 129.56/142.26 | +60.07% |
| ptime40 | 421.24 [277.22,446.06] | 408.04 [383.44,473.74] | 69.61/35.79 | -3.13% |
| same | 433.98 [364.22,441.86] | 441.22 [155.00,489.56] | 32.65/136.16 | +1.67% |
| transcode | 481.72 [433.46,519.12] | 482.42 [413.68,491.24] | 34.75/33.54 | +0.15% |

A dispersão é alta; diferenças pequenas não comprovam ganho nem ausência absoluta de regressão. O cenário ptime10 exige duas saídas em lugar de uma por entrada; o baseline fazia trabalho menor, porém emitia duração/timestamp incorretos. ptime40 agrupa duas entradas. Decode/encode e pacer têm custo legítimo. Não se atribui esse custo à validação isolada. A decisão de não criar fila encoded separada prioriza simplicidade e coordenação com os mecanismos existentes; ela não é apresentada como otimização de CPU.

| Cenário | Saídas antes/depois (cada run) | CPU µs/saída antes/depois (medianas) |
|---|---|---|
| same | [50]/[50] | 433.98/441.22 |
| ptime10 | [50]/[100] | 387.60/310.21 |
| ptime40 | [50]/[25] | 421.24/816.08 |

## Contadores de processo

Três repetições AB/BA com taskset no primeiro CPU permitido; perf task-clock/context-switches/cycles/instructions. Saídas individuais e comandos completos em `evidence/perf/`. `<not counted>` nos eventos de outro tipo de core não é zero. A tabela usa somente contadores core válidos com100% de runtime. Inclui startup/autoload/teardown, portanto não usa o mesmo denominador da janela getrusage acima.

| Cenário/variante | task-clock mediana ms | switches mediana | instruções core mediana |
|---|---:|---:|---:|
| same/before | 55.14 | 116 | 187975013 |
| same/after | 51.31 | 113 | 188219202 |
| dtmf/before | 50.02 | 117 | 189197418 |
| dtmf/after | 49.14 | 114 | 189645628 |
| ptime40/before | 53.24 | 114 | 187896540 |
| ptime40/after | 50.16 | 114 | 188183010 |
| idle/before | 27.56 | 3 | 174374375 |
| idle/after | 30.11 | 3 | 172891491 |

## Ociosidade e DTMF

Fallback ocioso1s,3 repetições por variante, CPU de janela getrusage (µs) e switches de SO. O teste edges mede também o número de chamadas recvfrom: uma leitura pendente em .6s e timeout em1s, sem polling. Não existem canais ou wakeups criados globalmente quando não há instâncias MediaChannel. Nativo mudou de200ms fixos para prazo/probe; a redução de wakeups nesse caminho é demonstrável pela política, sem extrapolar economia do worker completo.

- before: CPU [180, 166, 116], switches [2, 2, 2].
- after: CPU [176, 175, 119], switches [2, 2, 2].

Micro de1000 eventos END únicos, alternando dígito, com cache/guard reais, sem sleep. Mesmo workload antes/depois,5 repetições. O baseline perde callbacks; a candidata efetivamente executa1000, portanto não é comparação de trabalho funcional equivalente. Inclui varredura legada de cache e criação de rtpc/coroutine. Não mede uso de CPU do SipSwoole.

- before: µs/evento [17.391, 17.493, 18.601, 17.278, 17.998]; mediana17.493; DP0.548.
- after: µs/evento [24.323, 27.521, 24.658, 23.62, 24.373]; mediana24.373; DP1.515.

## Memória, latência e limites

Zend peak e ru_maxrss por execução estão nos JSON. São memória de processo, **não contagem de alocações**; não foi instrumentado allocator nativo. O novo estado de produção é um booleano privado por objeto rtpc, não cache por pacote persistido. Não se afirma tamanho exato físico ou redução de mallocs.

receive_interval_p50/p99 mede cadência de saída, não latência de processamento nem rede fim a fim. O micro DTMF registra wall_ns/evento, incluindo scheduler e callback. Latência exclusiva de cada método RTP e instruções exclusivas da classe não foram isoladas. Estes pontos permanecem limitações da comprovação, não valores presumidos iguais a zero.

Paridade acústica integral testada em `acoustic-parity.json`, além das métricas de canal com acústica ativa em todos os casos exceto noacoustic. Não houve otimização do algoritmo acústico. Não há comprovação de ganho global; também não se pode excluir regressão pequena sob ruído destes ensaios. A repacketização10ms apresenta aumento relevante por entrada, explicado pelo trabalho e saídas corretos. Recomenda-se benchmark consumidor prolongado antes de integração.

Micro DTMF reproduzível: executar `"$PHP" tests/mediachannel/dtmf-cost.php RAIZ` cinco vezes para cada raiz, alternando AB/BA. Resultados individuais em `dtmf-cost.json`; a sequência usa1000 timestamps distintos, evento i%16 e SSRC42. Nenhuma mudança de configuração de perf/sysctl foi feita.
