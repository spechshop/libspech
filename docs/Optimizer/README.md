# Optimizer — MediaChannel

Branch de revisão: [Optimizer](https://github.com/spechshop/libspech/tree/Optimizer). Base `0ccb9a3` da principal `spech`. Sem merge ou release.

1. [Diagnóstico](01-diagnostico.md)
2. [Plano de correção](02-plano-de-correcao.md)
3. [Revisão de código](03-revisao-de-codigo.md)
4. [Testes funcionais](04-testes-funcionais.md)
5. [Desempenho](05-desempenho.md)
6. [GitHub Checks](06-github-checks.md)
7. [Conclusão](07-conclusao.md)

`evidence/` contém resultados individuais, logs, contadores perf, hashes, ambiente e respostas do GitHub. `tests/mediachannel/` contém regressões permanentes e scripts de medição; a regressão roda na CI, benchmarks não bloqueiam PR.

As quatro reproduções passam após as correções, e a regressão automatizada está aprovada no GitHub. O comparativo não comprova ganho global de CPU. Repacketização10ms aumenta trabalho por entrada, com duas saídas corretas em vez de uma incorreta. Alocações individuais e latência exclusiva do processamento RTP não foram isoladas; essas limitações permanecem explícitas em05.

- [Correção de continuidade acústica durante DTMF](08-dtmf-acoustic-continuity.md)
