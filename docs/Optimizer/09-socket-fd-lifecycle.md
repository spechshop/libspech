# Ciclo de vida de sockets — INVESTIGAÇÃO PENDENTE

Data: 2026-11-14. Base libspech: `81a590e`, branch `Optimizer`.

A causa foi isolada e há uma correção nativa experimental com resultados locais positivos. **A correção completa não está certificada nem instalada.** O runtime publicado usado pela CI ainda retém FDs enquanto os objetos de sockets fechados estão referenciados. Não houve merge, release, troca do PHP instalado ou alteração do SipSwoole externo. A avaliação nativa em cópia isolada foi autorizada expressamente pelo usuário durante a investigação.

## Falha original e reprodução

F19 do laboratório `/home/lotus/CLionProjects/pcg729/validation/mediachannel-study` registrava +2/+6/+24 FDs para 1/4/16 canais. Consultados `02-referencias-e-problemas.md`, `tests/fds.php`, `tests/socket-control.php` e `tests/scale.php`. O teste original chama `requireNativeRtpParser()`, ausente nesta branch; não pode ser executado sem adaptação.

O controle antigo destruía o objeto antes de esperar. O teste de MediaChannel esperava **antes** de destruir os objetos e observava os FDs imediatamente depois de GC, sem uma nova passagem do reactor. Assim, comparava momentos diferentes do ciclo de vida. Acrescentar uma passagem pelo reactor após a destruição elimina a retenção daquela observação específica. Isso não elimina o segundo problema: objetos fechados ainda referenciados mantêm seus FDs indefinidamente, mesmo com o reactor avançando.

Comandos a partir da libspech:

```sh
php tests/mediachannel/socket-fd-control.php
php tests/mediachannel/fd-lifecycle.php . traditional /tmp/fd-traditional.json
php tests/mediachannel/fd-lifecycle.php . native /tmp/fd-native.json
python3 tests/mediachannel/run.py /tmp/libspech-media-php . /tmp/functional.json
python3 tests/mediachannel/measure-fd-lifecycle.py /caminho/php . /tmp/fd-measure 5
```

`socket-fd-control.php` não usa autoloader, MediaChannel, SocketMutable ou GC. Ambos os testes retornam código 1 quando a certificação falha. Procfs e APIs nativas ausentes são falha explícita. Nenhum SKIP é aceito como PASS.

## Ambiente e propriedade

Linux x86_64, PHP instalado 8.5.12 ZTS, Swoole 6.3.0RC1, epoll, build de 13/11/2026. A CI usa PHP 8.5.10 ZTS/Swoole 6.3.0RC1 do asset fixado em v1.1.7 de pcg729. Hashes e ambiente estão em `evidence/fd-lifecycle/`. O binário dessa release foi baixado e ensaiado localmente também.

A árvore nativa disponível está em `/home/lotus/CLionProjects/pcg729/source/php-src/ext/swoole`. Sua versão é 6.3.0RC1 e contém a implementação customizada de `recvfromInto`. Seu binário de build tem data/configuração diferentes do PHP instalado; não presumimos identidade binária. A reprodução independente comprova o mesmo comportamento nos três binários sem patch. Os trechos nativos, números de linhas e hashes foram preservados.

| Recurso | Proprietário e encerramento |
|---|---|
| RTP `$socket` | SocketMutable fornecido pelo chamador, referenciado pela MediaChannel. O objeto nativo possui o FD; o chamador pode guardar outra referência. A coroutine de `start()` possui a operação de leitura, não é proprietária exclusiva do objeto. |
| `$eventSock` | Criado no construtor, propriedade da MediaChannel. Não há leitura, escrita ou registro de evento sobre ele neste arquivo; apesar do nome, é um socket UDP adicional. |
| Recepção | A closure de `start()` mantém `$this` vivo durante a leitura; encerra no `finally`. |
| Pacers | Closures de coroutine mantêm `$this`; `active=false` e fechamento de `relayWake` encerram os consumidores. Sleeps de pacing já existentes podem completar antes de saírem. |
| Callbacks | `close()` limpa callbacks que podem capturar RtpSession. O callback de destruição permanece até a destruição; o padrão já é estático e não foi alterado. |
| Timers | Não existem `Timer::tick/after` próprios em MediaChannel. Timeouts de socket e sleeps usam timers internos do Swoole, encerrados ao completar suas operações. Não foi introduzido timer de limpeza. |

## Causa nativa comprovada

Fontes Swoole, não OpenSwoole:

- [Socket coroutine, tag v6.3.0-rc1](https://github.com/swoole/swoole-src/blob/v6.3.0-rc1/src/coroutine/socket.cc).
- [Socket network](https://github.com/swoole/swoole-src/blob/v6.3.0-rc1/src/network/socket.cc).
- [Métodos PHP](https://github.com/swoole/swoole-src/blob/v6.3.0-rc1/ext-src/swoole_socket_coro.cc).
- [Proposta upstream #6274](https://github.com/swoole/swoole-src/pull/6274), aberta na consulta, head `861a0dffbf5f97671b2d8772078e07d4db120cde`. Resposta e diff preservados, sem publicar comentários ou alterações no upstream.

Na árvore local:

1. `coroutine/socket.cc:1584`, `Socket::close()`: sem operações vinculadas, atribui `SW_BAD_SOCKET` a `sock_fd`. Isso é a condição de fechamento lógico. Não fecha `network::Socket::fd`.
2. Com operações vinculadas, marca `close_wait` e cancela leituras/escritas. `cancel()` (`:1559`) retoma a coroutine suspensa sincronamente. `wait_event` limpa `read_co/write_co` e remove o evento ao retornar da suspensão.
3. O método PHP `swoole_socket_coro.cc:1979` chama o fechamento nativo e retorna true; esse retorno não comprova `close(2)`.
4. O FD físico fica no `network::Socket`. A liberação ocorre pelo destrutor `coroutine/socket.cc:1616`, chamado por `socket_coro_free_object` (`swoole_socket_coro.cc:160`).
5. `network/socket.cc:479`, `Socket::free()`, remove o registro de evento, mas agenda `socket_free_defer` enquanto o reactor existe. Esse callback (`:457`) chama `close(fd)`.

Consequências: `isClosed()` verdadeiro não demonstra ausência no kernel. Não é necessário haver uma operação pendente para reproduzir. O reactor não precisa reter um objeto PHP: após sua destruição, basta a fila de callbacks adiados manter o socket de baixo nível até a próxima passagem pelo loop. Antes da destruição, a referência PHP, inclusive do chamador, basta para manter o FD. Depois de destruir e ceder ao reactor, o FD desaparece. Não foi comprovado vazamento permanente após o término do reactor.

O controle independente cobre socket nunca usado em leitura e socket cancelado enquanto bloqueado em `recvfrom`. Ambos retêm o inode com o objeto fechado vivo e liberam após destruição + passagem do loop. A ocorrência não é específica da MediaChannel nem de `recvfromInto`. A proposta upstream identifica precisamente esse defeito de fechamento da extensão. O adiamento de destruição é intencional; manter um FD utilizável no kernel durante toda a vida de um objeto já fechado é a limitação de API em avaliação upstream.

## Alteração PHP e revisão do diff

`MediaChannel::close()` agora cancela a leitura e, se o socket continua logicamente aberto, chama `destroy()`. Resolve o caso sem `start()`; também permite fechar a partir do próprio callback sem aguardar o topo do loop. Cancelar uma leitura suspensa já executa o fechamento na receptora, portanto a verificação evita repetir essa operação. Não há fechamento por número de FD, estado novo ou alteração no processamento RTP. O socket simulado em `test_media_channel_member_ptime.php` passa a implementar `destroy()` sem operação nativa, pois deliberadamente não chama o construtor nativo.

Esta alteração não resolve sozinha o problema nativo e não é apresentada como solução definitiva. Complexidade adicional O(1) por `close()`, zero trabalho periódico. Arrays de membros e DSP mantêm a limpeza existente. A revisão separada do diff verificou propriedade, retorno síncrono de cancelamento, reentrância, callbacks, ausência de novos ciclos e manutenção do caminho RTP. Permanecem duplicações históricas de encerramento no loop/finally e catches silenciosos existentes; não foram refatoradas nesta tarefa. SocketMutable mantém sua flag histórica, que não comprova fechamento físico.

## Avaliação isolada autorizada

Foi copiada a árvore de PHP para `/tmp/libspech-fd-native/php-src`, preservando os fontes originais. Caminhos absolutos de Makefile/libtool e metadados `.dep/.lo/.la` foram redirecionados à cópia. Adaptados os cinco arquivos de implementação da proposta upstream para a versão 6.3.0RC1 local. Patch exato em `native-evaluation.patch`; é **experimental Linux**, não um pacote de produção nem patch oficial aplicado integralmente. A adaptação substitui `ssl_shutdown()` da versão antiga pela limpeza `ssl_close()` existente na árvore atual. O trecho Windows da liberação não foi portado; não utilizar essa adaptação em Windows.

```sh
patch -p1 -d /tmp/libspech-fd-native/php-src/ext/swoole < native-evaluation.patch
env PATH=/usr/local/musl/bin:/usr/local/musl/output/bin:/usr/bin:/bin \
  make -C /tmp/libspech-fd-native/php-src -j2 sapi/cli/php
```

Foi reutilizado o build configurado, com no máximo dois compiladores. O patch separa `close_fd()` da destruição, remove o socket do reactor antes de fechar, invalida o FD físico após fechamento e impede que o callback adiado feche um número reutilizado. A ponte PHP termina o fechamento após cancelamentos quando já não há operações vinculadas. O objeto PHP pode continuar vivo e consultar `isClosed()`.

Soluções descartadas: close/cancel repetidos não alcançam a liberação física; GC forçado não substitui ownership; remover propriedades públicas não elimina referências do chamador e muda contrato; fechar por inteiro numérico ou via FFI deixa o proprietário nativo apto a fechar um FD reutilizado; polling, timers periódicos, término de worker e reinícios não resolvem o ciclo de vida. Importar streams para mudar o ownership exige outra arquitetura de construção e validação de compartilhamento; não é um workaround mínimo seguro para SocketMutable existente.

Riscos nativos: SSL/DTLS, compartilhamento com streams importados, mapa de sockets de hooks, concorrência com escrita suspensa, threads e caminhos UNIX. Os corpos dos três testes upstream TCP/UNIX/stream passaram com Assert original e bootstrap reduzido. O harness PHPT completo **falhou no bootstrap** por ausência de `posix_access()` no build experimental; não é registrado como PASS. Não foram certificadas todas as combinações SSL, threads e exceções externas. A avaliação autorizada não equivale a autorização para substituir ou publicar runtime.

## Testes e evidências

`fd-lifecycle.php` executa seis cenários × séries de 1/4/16/50 canais, mantendo um único reactor ativo. Modos tradicional e nativo em processos separados, APIs nativas obrigatórias. Criação/start em coroutine diferente de close; tráfego RTP PCMA, callback reentrante com close duplo, inatividade normal, canais não iniciados, membros/probes/DTMF recebido/métricas e tap acústico ativos. Uma MediaChannel sentinela permanece ativa durante todas as séries e recebe RTP ao final. Identidade é `socket:[inode]`, sem confundir números reutilizados de FD. Registra snapshots completos (destinos incluídos), estados e CIDs, memória, rusage e controle após saída do reactor.

O prazo é 50 ms após close: 10 ms para observar conclusão, mais 40 ms antes da certificação. Um callback adiado precisa de uma passagem pelo loop; a margem cobre múltiplas passagens no ensaio local limitado. Depois de remover as referências, há ainda um controle de 10 ms. São esperas finitas do teste, sem polling e sem `gc_collect_cycles()`.

| Momento, canais 1/4/16/50 | Sem patch nativo | Com patch nativo + correção PHP |
|---|---|---|
| Objetos fechados ainda vivos, após 50 ms | 2 / 8 / 32 / 100 sockets | 0 / 0 / 0 / 0 |
| Objetos destruídos + passagem do reactor | 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 |
| Receptoras pendentes depois de close | 0 | 0 |
| Sentinela mantém FD e recebe RTP | sim | sim |
| Objeto MediaChannel retido após descarte | não | não |

Resultados iguais para recepção tradicional/nativa, nos cenários de canais iniciados. Antes da correção PHP, o cenário não iniciado também mantinha `rtp_closed=false`; depois, ambos os sockets ficam logicamente fechados. Evidências `before-*`, `php-fix-*`, `native-patch-*`, controles independentes e séries de medição contêm resultados individuais. Apenas o runtime experimental passa os três novos checks. A regressão funcional final experimental aprova os 17 comandos, incluindo RTP, DTMF, packetização, codecs, conversão PCM, gravação e acústica.

Cobertura ainda incompleta: exceções que escapam de callbacks, escrita UDP realmente bloqueada, DTMF de saída suspenso simultaneamente ao fechamento, e soak prolongado de produção. Esses limites impedem certificar a correção geral mesmo além da dependência de distribuição do runtime.

## Desempenho

Cinco repetições por modo/configuração, dez execuções por configuração; cada execução contém os 24 ciclos. Dados completos em `measure-before`, `measure-source-before`, `measure-native-patch`. O primeiro usa PHP instalado; o segundo, binário sem patch da árvore usada no rebuild, e é o comparativo mais próximo. `measurement-summary.json` facilita comparar o cenário bloqueado de 50 canais. `perf` mede task-clock e context switches do processo completo; `getrusage` mede CPU/context switches do trecho close e da janela ociosa de 100 ms. Não confundir task-clock do processo com custo exclusivo de fechamento.

A mediana de CPU de close por canal no cenário bloqueado/50 foi 36,52 µs no build da árvore sem patch e 46,79 µs com patch (aproximadamente +10,27 µs/canal, +28%). A mediana de parede foi 36,11 versus 46,40 µs/canal. O custo legítimo agora inclui o fechamento físico e remoção de recursos que antes eram postergados. Não se afirma ganho de CPU global.

A observação após close já não encontra os inodes no runtime experimental; no antigo ainda os encontra aos 50 ms. Isso dá limites observados, não latência individual de syscall. Memória Zend e máximo RSS são preservados por ciclo; o próprio teste guarda snapshots/resultados e seu crescimento de memória não deve ser atribuído ao canal. RSS máximo não mede memória residente atual. Não há certificação de ausência de crescimento de memória em soak longo. Nenhum caminho periódico novo foi adicionado à libspech.


Métricas do processo completo e ociosidade (mediana [mínimo, máximo], dez execuções):

| Métrica | Build fonte sem patch | Experimental |
|---|---:|---:|
| task-clock ms | 223.38 [194.41, 238.40] | 198.18 [120.91, 218.44] |
| context switches (perf) | 117.50 [112.00, 127.00] | 114.50 [111.00, 123.00] |
| CPU ociosa / 100 ms (µs) | 102.00 [78.00, 114.00] | 97.00 [45.00, 121.00] |
| context switches ociosos | 1.00 [1.00, 1.00] | 1.00 [1.00, 1.00] |

## GitHub Actions e conclusão

O workflow existente `.github/workflows/mediachannel.yml`, job **PHP and media regression**, executa os três novos comandos via `tests/mediachannel/run.py`; o mesmo runner continua executando as regressões anteriores. Não foi criado workflow redundante, não houve alteração de proteção de branches e o runtime continua fixado por SHA256. O teste estrito deve falhar no runtime atual, em vez de ocultar a retenção.

O resultado real após push será registrado em `github-actions.json` e abaixo. Não há link de execução aprovada a apresentar enquanto a CI usar o runtime sem correção nativa. Falta distribuir/aprovar um runtime corrigido, validar os riscos nativos e os cenários pendentes e obter o check verde com os testes estritos. Estado: **INVESTIGAÇÃO PENDENTE**.

Resultado real consultado: [run 43574686096](https://github.com/spechshop/libspech/actions/runs/43574686096), commit `2cab360ff094ade17759584da491a9dae5369822`, **failure** em **PHP and media regression**. Os 14 comandos anteriores passaram; os três novos comandos de FD reprovaram no runtime publicado, conforme reprodução local. Log integral comprimido em `github-actions.log.gz`; metadados em `github-actions.json`. O commit de registro posterior altera somente relatório/evidências. Não existe aprovação dos novos checks para entregar.

Os arquivos `*.log.gz` preservam logs individuais e de build sem depender das regras de exclusão de `*.log` do repositório.
