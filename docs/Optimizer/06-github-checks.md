# 06 — GitHub Checks

Nenhum workflow existia na referência inicial. Adicionado somente `.github/workflows/mediachannel.yml`, push em `Optimizer`, PR para `spech` ou `Optimizer`. Job visível: **PHP and media regression**. Permissão apenas contents:read, limite10min, sem benchmark como bloqueador.

Runtime publicado fixo `spechshop/pcg729` v1.1.7, PHP8.5.10 ZTS, psampler0.7.0; SHA256 validado antes de executar. Disponibilidade de Swoole, psampler, bcg729, opus e GSM é obrigatória; falha de download/dependência falha o job. O caminho receive nativo adicional depende de Buffer/recvfromInto e segue a seleção real da biblioteca. Parser nativo e regressões são exercitados com psampler. Runtime não é publicado ou modificado nesta etapa.

Checks: sintaxe de plugins, tests e test_*.php rastreados; runner13 entradas, incluindo reproduções A–D, edge cases, codecs, parser e lifecycle; JSON e lint no log, tabela no resumo mesmo em falha. Runner não converte SKIP em sucesso.

Proposta para proteção: exigir o check **PHP and media regression**, após verificar execuções no PR e eventuais políticas de organização. Nenhuma proteção/ruleset foi alterada. Branch principal preservada, sem merge/release.

Primeira execução: [43551037796](https://github.com/spechshop/libspech/actions/runs/43551037796), commit `d71986c`, `startup_failure`, sem jobs. Investigação read-only encontrou `allowed_actions=local_only`, `sha_pinning_required=true`. Não era falha dos testes. Workflow foi ajustado para checkout por git e publicação do resumo pelo shell/Python do runner, sem ações externas e sem mudar políticas. Logs JSON permanecem na execução, sem upload-artifact (também proibido).


Execução corrigida: [43551313851](https://github.com/spechshop/libspech/actions/runs/43551313851), commit `ea049a6`, **success**. Job **PHP and media regression** aprovado: checkout, runtime/hash/dependências, sintaxe e regressão. Resposta GitHub com etapas/status preservada em `evidence/github-success.json`. Não confundir esta execução com a inicial recusada por política.
