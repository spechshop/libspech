# 01 — Diagnóstico

Base oficial: `spechshop/libspech`, branch principal `spech`, commit `0ccb9a3fbcec06c5feb32162b704b866052c098d`. `Optimizer` foi criada dessa referência após fetch. Checkout oficial `/home/lotus/PhpstormProjects/libspech` limpo; a branch local `spech` antiga foi preservada. O laboratório pcg729 possuía alterações locais, todas preservadas. Não houve alterações no SipSwoole.

Fontes da investigação: `pcg729/validation/mediachannel-study/01…08`, testes e patches em `tests/` e `evidence/`. O laboratório tinha uma versão diferente: native receive ligado por padrão e método `requireNativeRtpParser`; a versão oficial tem native receive desligado por padrão e não possui esse método. Não foram copiados os patches completos nem a otimização acústica rejeitada.

Referências anteriores, verificáveis por `git show 0ccb9a3:plugins/Utils/sip/MediaChannel.php | nl -ba`:

| Problema | Causa na versão oficial | Referência anterior | Referência corrigida |
|---|---|---|---|
| A | Callback somente no segundo END, condicionado à subtração não modular de sequência. Repetir o mesmo segundo END também repetia callback. | `processDtmf`, 2392–2406 | `processDtmf`, 2408 e 2500 |
| B | `recvfrom($peer,-1)` não retorna em silêncio. Política de timeout/probe só roda após receive retornar. | `start`, 703 e 708–780 | `start`, 694–710 e 739 |
| C | Parser permissivo retorna defaults em pacote curto; validação dependia de métricas, e CSRC/extensão truncada pareciam payload vazio legítimo. | `start`, 797–809; `rtpc::__construct`, 29–72 | `rtpc::isValidRtpPacket`, 125; `start`, 807–818 |
| D | Fast relay comparava ptime configurado, mas `buildAudioPacket` avança samplesPerPacket independentemente do tamanho enviado. Resíduos também podiam ser ultrapassados por relay direto. | `start`, 967–982; `rtpChannel::buildAudioPacket` | `start`, 973–999 |

As referências corrigidas são relativas ao commit de implementação `d71986c`. Retenção de FDs e perdas acústicas durante DTMF permanecem fora do escopo. As métricas acústicas dos benchmarks permitem observar a interferência, sem reclassificar esses eventos como perda de rede.
