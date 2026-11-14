# Continuidade acústica durante RFC 4733

Baseline: `f7c26953386cb4a32f7016346a8b553231a3bc9d`, branch `Optimizer` de `spechshop/libspech`. Nenhuma alteração no SipSwoole externo, na `spech`, em proteções ou releases.

## Causa e efeitos observáveis

Na baseline, `plugins/Utils/sip/MediaChannel.php:927–935` encaminha/processa telephone-event e executa `continue`. Os taps de áudio ficam em `:952–957` e `:1042–1045`, depois dessa saída. Tanto recvfrom quanto recvfromInto convergem nesse caminho (`:803–805`). `processDtmf` (`:2371–2512`) trata término, debounce e retransmissões para entregar o dígito; isso não substitui observação de cada sequência RTP recebida. Os END repetidos possuem sequências novas, mesmo quando timestamp/duração são iguais.

`AcousticRtpContinuity.php:23` da baseline soma delta−1 sem conhecer os eventos recebidos. A MediaChannel já vê todos os pacotes em suas próprias métricas; por isso seus contadores podem discordar dos acústicos. O tap (`EarlyMediaAcousticTap.php:68–78` da versão corrigida) transforma a indicação em reset ou fail-open do decodificador compartilhado. `EarlyMediaAcousticMonitor::discontinuity` (`plugins/Utils/audio/EarlyMediaAcousticMonitor.php:95–108`) descarta PCM parcial, evidência vocal e ringing, avança a época e reseta o analisador. Logo, o defeito pode atrasar/impedir uma decisão acústica e até encerrar a análise G729/Opus com `shared_decoder_packet_loss`.

Além de sequência, comparar o próximo timestamp de áudio diretamente com áudio após um evento suprimido produz `timestamp_jump`; medir chegada somente entre áudios produz `arrival_gap` em eventos longos. Apenas zerar o contador deixaria esses efeitos intactos.

Reprodução real por socket, acústica ligada: `php tests/mediachannel/bench.php . dtmf 40`. Baseline: 40 pacotes recebidos, 37 áudios, 3 DTMF; MediaChannel lost=0, tap lost=3, época=1, reset packet_loss. Corrigido: mesmos 740 ms / 37 frames acústicos, lost=0, época=0, aberto. JSONs em `evidence/dtmf-continuity/`. O teste novo contra a baseline também termina com status 255 em `event time`, acusando `timestamp_jump` (resultado preservado).

## Solução e invariantes

* A única inclusão na MediaChannel é observar telephone-event no tap antes da saída existente (`MediaChannel.php:927–931`). Não altera encaminhamento, callback DTMF ou decodificação.
* O tap usa seu método existente `receive`, limita-se à perna b e trata o evento como observação de transporte. Retorna antes de converter/decodificar/empurrar PCM (`EarlyMediaAcousticTap.php:43–85`). Duplicata de DTMF não contaminou decodificador compartilhado e não deve disparar o fail-open reservado às duplicatas de áudio.
* A continuidade mantém sequência/SSRC/chegada para todo RTP observado e formato/próximo timestamp para áudio. Não confunde PT telephone-event com mudança de codec. `inspect` recebe um argumento opcional interno `eventRate`; os chamadores antigos continuam válidos.
* Uma única extremidade temporal `event_end` cobre o intervalo comprovado por timestamp+duração recebidos, no mesmo clock. Só estende cobertura quando o início toca/sobrepõe a timeline conhecida. Não exige END, não infere duração de pacotes ausentes, nem conta retransmissão de END como novo tempo. Áudio pode continuar em paralelo ou retomar dentro/ao final desse intervalo. Salto além dele, para trás ou não ancorado continua resetando. Duração de áudio consome a cobertura; não insere silêncio nem conta DTMF como voz.
* Chegada usa o último pacote aceito, inclusive DTMF. Eventos longos com chegadas regulares não resetam por ausência de áudio. Hiato real >200 ms em qualquer trecho permanece detectável.
* Delta de sequência é modular de 16 bits, timestamp de 32 bits. A lacuna real é contabilizada imediatamente, mesmo se o próximo pacote for DTMF. A sequência não é inferida pelo número de ENDs.
* Dois inteiros formam uma janela fixa de 63 sequências para distinguir duplicatas de chegadas tardias e reparar uma estimativa quando o pacote faltante chega. Sem essa memória, o contador continuaria marcando como perdido um pacote reordenado recebido depois da lacuna. PCM tardio não é reproduzido no analisador. O reset na descoberta da lacuna permanece: a análise já recebeu áudio descontínuo e não pode reconstruir evidência retroativamente.

Complexidade O(1) por pacote e O(1) por tap: dois inteiros para janela e uma extremidade dentro do estado já existente. Sem timer, lista por evento, cache DTMF adicional, novo DSP ou alteração no monitor/AcousticStreamAnalyzer. Foi evitada cópia adicional do array de continuidade no caminho de áudio. Diff de produção: 74 inclusões e 20 exclusões, três arquivos; ver `git show --stat` e `git diff f7c2695 -- plugins/Utils/sip/{MediaChannel,AcousticRtpContinuity,EarlyMediaAcousticTap}.php`.

## Testes e integridade acústica

`tests/mediachannel/acoustic-dtmf.php`: tempos injetados na continuidade tornam os limites temporais determinísticos; matriz por sockets usa pacotes ordenados em loopback e verifica métricas, abertura, época e entrega de eventos. 160 combinações (2 recepções × 2 modos de canal × 4 codecs × 10 casos), incluindo duas pernas por canal e recriação/fechamento em cada combinação.

Cobertura: áudio contínuo; DTMF intercalado/concomitante; progress/END único e ENDs repetidos; retransmissões, duplicatas e reordenação; perda real de áudio, DTMF e áudio durante evento; wrap de sequência e timestamp; SSRC; troca de codec; salto temporal coberto, além da cobertura, retrógrado e não ancorado; evento longo sem chegada de áudio e gaps reais antes/depois de DTMF; PCMA/PCMU/G729/Opus; nativo/tradicional, relay/receive-only; isolamento da perna a; fechamento idempotente e tap novo com DTMF antes do primeiro áudio.

Comparação direta com áudio equivalente sem DTMF verifica todas as métricas do monitor (frames por classificação, evidência vocal, bytes PCM pendentes, época, encerramento, falha e decisão), excluindo somente tempo de execução e contadores de duplicação/reordenação do transporte. Compara também eventos por tipo, posição acústica, época e sinal. Cobre PCM compartilhado e decodificação no tap; a matriz usa os decodificadores reais. G729/Opus compartilhados têm controles negativos explícitos: lacuna verdadeira causa época=1, lost=1 e `shared_decoder_packet_loss`; duplicatas de DTMF não fecham o monitor. Não é um teste apenas de lost=0.

A suíte completa local passou: **14 comandos**, sem SKIP. Resultados em `evidence/dtmf-continuity/functional.json`. O novo teste integra `tests/mediachannel/run.py`, já executado pelo check **PHP and media regression** do workflow existente `.github/workflows/mediachannel.yml`; nenhum workflow novo.

## Desempenho

PHP 8.5.12 ZTS, Swoole 6.3.0RC1, mesma máquina/binário/entradas, acústica ativada em ambos os lados. Baseline extraída por `git archive` de f7c2695. Sete repetições alternando antes/depois, 20.000 iterações no tap e 60 pacotes a 50 pps na MediaChannel por repetição. CPU = getrusage user+system, sem confundir o processing_ns acústico (tempo de parede) com CPU. Valores em µs por RTP:

| Escopo / cenário | Antes mediana [mín–máx]; DP | Depois mediana [mín–máx]; DP |
|---|---:|---:|
| tap+DTMF handler / audio | 3.498 [3.454–3.566]; 0.039 | 3.796 [3.723–3.830]; 0.042 |
| tap+DTMF handler / event | 12.088 [12.033–12.173]; 0.065 | 9.031 [8.768–9.060]; 0.138 |
| MediaChannel paced 50pps / acoustic | 368.817 [212.600–436.617]; 85.234 | 399.917 [169.167–435.067]; 119.566 |
| MediaChannel paced 50pps / dtmf | 405.717 [161.417–446.300]; 107.261 | 430.050 [146.783–483.783]; 145.979 |

Custo isolado de áudio: +0,299 µs/RTP (+8,5% do pequeno custo do tap), aproximadamente **15 µs de CPU por segundo por fluxo de 50 pps**, ou 0,0015% de um núcleo. É trabalho adicional para contabilizar o transporte corretamente e identificar reordenação. Não é apresentado como otimização de CPU. Na MediaChannel os intervalos se sobrepõem amplamente; os ensaios curtos/temporizados não demonstram equivalência estatística nem regressão atribuível ao patch. Medianas passaram de 368,817 para 399,917 µs/RTP no cenário contínuo; esses números incluem socket, scheduler, relay e instrumentação.

No ensaio de evento, cada iteração inclui **um áudio + três pacotes END + três chamadas ao processDtmf existente**. CPU por evento/iteração: 48,353 → 36,125 µs (baseline resetava indevidamente conversor/analisador em cada evento). Não representa custo puro de uma instrução nem ganho garantido em produção. Na linha MediaChannel/dtmf há um evento de três pacotes por execução, não um evento por RTP.

Instruções: cinco repetições com `taskset -c 2 perf stat -x, -e cpu_core/instructions/` (PMU de núcleo, sem somar domínios híbridos). Inclui startup do PHP, diluído em 20.000 iterações. Áudio: mediana 65.227,70 → 68.418,28 instruções/RTP; faixas 65.213,54–65.236,92 e 68.402,84–68.429,56; DP 8,61 e 9,76. Evento: 156.101,72 → 73.071,90 instruções/RTP; DP 668,97 e 566,20. Cada evento contém quatro RTP, conforme explicado acima. Dados crus em `instructions.json`.

Memória retida no ensaio contínuo: 67.008 bytes antes e depois; pico 4.927.800 → 4.940.216 bytes, incluindo carregamento do código. Não há contador de alocações do Zend disponível: esses números medem bytes retidos/pico, **não número de alocações**. O cache existente do processDtmf domina a memória do ensaio intensivo de eventos (~9,7 MB retidos); o patch não cria outro cache. Nenhum crescimento por evento foi acrescentado à continuidade.

Reproduzir: extrair f7c2695 em outro diretório com `.env` vazio e executar `python3 tests/mediachannel/measure-acoustic-dtmf.py /caminho/baseline /caminho/Optimizer`. O estágio perf requer Linux, core 2 e PMU cpu_core; adaptar afinidade/PMU para outra máquina. Evidências mantêm todas as repetições, não somente médias.

## Limitações e riscos deliberados

A perda é estimativa imediata pelo maior número de sequência, corrigível em até 62 posições anteriores. Pacotes mais antigos ficam como late sem reduzir lost; saltos de meia faixa (32768) ou mais são ambíguos pelo próprio espaço RTP e não são usados para avançar estado. Implementação usa inteiros PHP de 64 bits, como os runtimes de mídia locais e do CI. Não introduz jitter buffer ou atraso à decisão para esperar reordenação.

Cobertura temporal exige o mesmo clock negociado para áudio e telephone-event. Com clocks diferentes, a sequência continua correta e áudio temporalmente contínuo não reseta; não se inventa transformação entre origens RTP diferentes. Um salto de áudio sem mapeamento temporal comprovado continua gerando reset conservador. Cobertura além da duração de eventos efetivamente recebidos (por exemplo, último END perdido) também não é inventada. Pacotes malformados continuam invalidando evidência. Não se transforma ausência de informação em áudio válido.

A matriz determinística usa silêncio nos codecs para conferir preservação exata do estado/PCM. Adicionalmente, `acoustic-dtmf-voice.php` passou com a fixture real local `/home/lotus/projetos/psampler/tests/acoustic-fixtures/speech.pcm`: áudio codificado PCMA, eventos a cada 300 ms e comparação integral de métricas, decisão (excluindo confirmed_ns) e eventos contra o áudio sem DTMF. Resultado e SHA-256 em `evidence/dtmf-continuity/voice-parity.json`. A fixture externa não foi copiada para o repositório; esse ensaio de laboratório não integra o CI e não certifica a precisão classificatória para toda voz real. O analisador, suas políticas e seus limiares não foram alterados. As medições de socket são locais, não um teste de capacidade sustentada em ambiente produtivo.

## GitHub Actions

Pendente de push e consulta do resultado real. O registro será atualizado após execução remota do check existente.
