# 03 — Revisão de código

Revisão posterior da implementação comparada a `0ccb9a3`, via `git diff`, `git diff --check`, testes e duas versões do runtime.

- A: nenhum estado novo. Removida condição de sequência e callback duplicado; cache `processed`, último evento e guard temporal continuam responsáveis por deduplicação/debounce. Progress atrasado não reabre evento. A política legada de debounce não foi ampliada.
- B: três variáveis locais de prazo/relógio substituem espera infinita ou polling fixo. Nenhum timer/coroutine adicional. `lastSilenceProbeAt` e `lastPacketTime` existentes definem o prazo. Removidos `round` e elapsed duplicado. O primeiro probe espera intervalo de silêncio, evitando probe imediato por `lastSilenceProbeAt=0`. Timeout alterado durante uma leitura pendente vale na próxima leitura; consumidores devem configurar antes de start, como a API existente permite.
- C: um booleano privado por rtpc distingue sucesso estrutural de retorno parcial. Duas atribuições nos dois produtores existentes, sem novo parse. A consulta usa flags já calculadas, sem materializar rawPacket nativo. Payload/rawPacket antigos do parser permanecem compatíveis; só a admissão rejeita inválidos. Padding só custa leitura/substr quando P=1. Contagem e refresh de atividade foram movidos após validação.
- D: `isG711` calculado uma vez por entrada; `targetChannel` e `sameDuration` locais evitam uma condição longa. Não há buffer/cache/coroutine nova. O pacer existente permanece dono do destino após iniciar: não permitir bypass evita ultrapassar um frame em envio/sleep, mesmo se buffer momentaneamente vazio. Isso conserva decode/encode em pacotes posteriores desse destino até teardown. Alternativa de aposentar pacer exige outra revisão de concorrência.
- Fixture preexistente: mock sem socket nativo agora sobrescreve cancel; callback GSM verifica payload original, pois o callback precede o decode de relay. O teste continua verificando RTP GSM→PCMA e rejeição de payload inválido; não foi removido nem ignorado.
- Testes importados do laboratório foram adaptados, formatados e ampliados. `flows.php` agora falha efetivamente em contrato falso. Runner falha em SKIP, exit não zero ou timeout. Benchmarks ficam fora dos gates.

Nenhum método antigo sem relação com as correções foi removido. Não foram alteradas política acústica, SDP, codecs nativos ou assinaturas existentes. O método novo de rtpc é aditivo. `complete` participa da serialização privada padrão; round-trip/clone/string/native permanecem testados.

Limites: não foi homologado restart após close nem concorrência entre injeção/DTMF simultâneos. O cache DTMF e mapas antigos continuam com suas políticas existentes; não foram criados novos caches para ocultar essas questões.
