# Acoustic Lab — libspech

Prototipo local de monitoramento incremental de ringback e fala com `AcousticStreamAnalyzer` do psampler.

## Instalar

Coloque a pasta `acoustic-lab` em `~/projetos/libspech/extra/acoustic-lab/`.

A partir da raiz da libspech:

```sh
~/CLionProjects/pcg729/buildroot/bin/php extra/acoustic-lab/server.php
```

Abra `http://127.0.0.1:9510` no navegador.

O seletor encontra WAVs na raiz do projeto `libspech` e na subpasta `audio/`. Use "Adicionar WAV" para enviar um arquivo. Limite: 32 MiB, 15 minutos. Formato: WAV RIFF PCM linear 16-bit, mono ou stereo, 8–192 kHz. O servidor usa `stereoToMono()` e `Resampler` quando necessário para entregar PCM16LE mono 8 kHz ao detector.

## Funcionamento

- Reproducao nativa no navegador por `audio`.
- Uma conexao SSE por analise envia eventos e status, enquanto o servidor envia `push()` a cada 20 ms de tempo de parede.
- Atualizacao de status a cada 100 ms; eventos imediatamente apos cada `push()`.
- Cada conexao tem seu proprio `AcousticStreamAnalyzer` e estado isolado.
- Os timestamps dos eventos sao os timestamps de audio do psampler, **nao** timestamps do relogio de parede.
- O inicio aguarda os metadados, o reposicionamento interno e o evento `ready` do analisador antes de chamar `play()`. Cada sessao invalida as operacoes assincronas da anterior.
- Eventos e estados recebidos por SSE ficam em fila ate `audio.currentTime` atingir seus timestamps. O cursor e o progresso seguem o relogio do player; atualizacoes acusticas nao alteram `src`, nem chamam `load()`, `play()` ou `pause()`.
- `ring.started` tem qualificacao de tom (~1 s) e `ring.interrupted` tem regras temporais; a linha do tempo exibe a confirmacao, nao a borda fisica retroativa.

### Observacoes

Ferramenta de laboratorio: nao encaminha RTP, nao faz chamadas e nao usa SIP. O servidor continua analisando incrementalmente durante uma pausa do player; os resultados ficam em memoria no navegador e so aparecem quando o audio chega a posicao correspondente. A entrega por SSE pode acrescentar um pequeno atraso visual.

Pause pelo controle nativo e retome pelo player ou por "Continuar analise", preservando a sessao e o estado do `AcousticStreamAnalyzer`. "Parar" cancela a sessao; "Iniciar analise" recomeca do inicio. E possivel selecionar outro arquivo durante a reproducao, cancelando a sessao anterior. O termino da analise no servidor nao interrompe o audio: a sessao so conclui depois que o player termina e os resultados pendentes sao exibidos.

Reposicionar manualmente ou mudar a velocidade encerra a sessao para nao misturar posicoes diferentes; inicie novamente para analisar desde o inicio, em velocidade normal. Falhas reais de carregamento, reproducao e conexao aparecem em vermelho; cancelamentos por pausa, parada ou troca de arquivo nao sao tratados como falha.

O servidor escuta apenas `127.0.0.1:9510`. Para laboratorio remoto, prefira tunel SSH, nao exponha diretamente na internet. Nao use com arquivos nao confiaveis sem isolamento adicional. O diretorio de audio e local e os uploads ficam salvos nele.

## Validacoes sugeridas

- `rec_postal.wav`: 6 eventos `ring.started`, 6 `ring.ended`, cadencia de 5 s e entrada de fala por volta de 31,2 s na versao corrigida do psampler.
- Compare instantes `speech.started` com o grafico de tempo de audio.
- Arquivos stereo e com outra amostragem devem passar pelo downmix/resample.

Nenhum componente da libspech ou do psampler e modificado pelo prototipo.

## Testes de regressao no navegador

Com o servidor acima em execucao, em outro terminal:

```sh
cd extra/acoustic-lab
npm ci
npm test
```

Requer Node.js e Chrome (`/usr/bin/google-chrome` por padrao). Use `CHROME_BIN=/caminho/do/chrome npm test` para outro executavel, ou `npx playwright install chromium` se nao houver Chrome instalado. `ACOUSTIC_LAB_URL` permite definir outra URL para o servidor local.

Os testes executam Chrome headless com o servidor e o `AcousticStreamAnalyzer` reais, criam WAVs temporarios mono 8 kHz e stereo 44,1 kHz em `audio/` e os removem ao terminar. Cobrem primeira reproducao, pausa/retomada, parada durante carregamento e `play()` pendente, troca de arquivo, cliques rapidos, reinicio apos o termino, alinhamento dos timestamps e tratamento separado de erros reais e cancelamentos intencionais. Atrasos e falhas especificos sao injetados no navegador para reproduzir as corridas.
