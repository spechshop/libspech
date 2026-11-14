# 02 — Plano de correção

| Caso | Menor solução segura escolhida | Alternativas e custo |
|---|---|---|
| A | Disparar após os guards existentes, ao aceitar o primeiro END; remover disparo em retransmissões. | Nenhum timer/cache novo. Mantém coroutine e quatro argumentos do callback. Um callback passa a existir onde antes se perdia; duplicados deixam de criá-lo. |
| B | Prazo restante de inatividade, limitado pelo próximo probe quando há membros e probes habilitados. `close()` continua cancelando a leitura. | Rejeitados polling de 200 ms e timer separado. Acrescenta relógio/subtração/min/max por receive; remove round e cálculo duplicado. Sem membros não há wake periódico. Nativo usa o mesmo prazo. |
| C | Registrar sucesso estrutural no parser e consultar validade na admissão. Manter parser permissivo e seu payload público. Retirar padding validado apenas na MediaChannel. | Validar bytes no transporte repetiria o parse de CSRC/extensão e leituras do buffer nativo. Um booleano privado `complete` é necessário: payload vazio válido e parse abortado têm os mesmos campos públicos. Um método público aditivo permite consultar esse resultado sem expor estado mutável. Não foram introduzidos parâmetros ou exceptions no parser. |
| D | Calcular duração G711 pelos bytes reais. Relay direto quando formato/duração coincidem e não há fila/pacer; demais casos reutilizam fila PCM e pacer. | Fila encoded separada exigiria estado adicional, limpeza e coordenação com PCM/DTMF/injeção. Não integrada. Decode/encode G711 adiciona custo legítimo quando a duração difere, com preservação das amostras decodificadas. Evita recode quando a entrada já atende o destino, mesmo com ptime declarado diferente. |

Não se reivindica economia global de CPU. C é uma validação necessária; D pode gerar mais pacotes corretos e mais trabalho. Medir essas diferenças é parte da decisão. Cache persistente de mapas públicos, alterações do DSP e refatoração de lifecycle não fazem parte desta etapa.
