# 🧪 Guia de Testes - libspech

Este documento descreve todos os testes disponíveis para validação do projeto libspech.

---

## 📋 Índice

1. [Testes Unitários](#testes-unitários)
2. [Testes de Integração](#testes-de-integração)
3. [Estrutura de Arquivos](#estrutura-de-arquivos)
4. [Resultados](#resultados)

---

## 🎯 Testes Unitários

### 1. RTP Channel Tests (48 testes)

Valida a classe `rtpChannel.php` com conformidade RFC 3550 e RFC 2833.

```bash
php run_tests.php
```

**Cobertura:**
- ✅ Constructor & Validation (5 testes)
- ✅ RTP Header RFC 3550 (8 testes)
- ✅ Audio Packets (4 testes)
- ✅ DTMF Forward (6 testes)
- ✅ DTMF RFC 2833 (12 testes)
- ✅ Sample Rate Conversion (5 testes)
- ✅ Edge Cases (7 testes)
- ✅ Channel Info (1 teste)

**Tempo de execução:** ~0.003s

---

### 2. Media Channel Tests (51 testes)

Valida a classe `mediaChannel.php` incluindo codecs, VAD, DTMF e gerenciamento de membros.

```bash
php run_media_tests.php
```

**Cobertura:**
- ✅ Codec Resolution (6 testes)
- ✅ Frequency Resolution (4 testes)
- ✅ SSRC Generation (5 testes)
- ✅ PCM Mixing (6 testes)
- ✅ PT Codec Registration (5 testes)
- ✅ VAD Configuration (5 testes)
- ✅ DTMF Translation (13 testes)
- ✅ Telephone Event PT (2 testes)
- ✅ Member Management (5 testes)

**Tempo de execução:** ~0.002s

---

## 🔗 Testes de Integração

### Teste Paralelo - 5 Instâncias Simultâneas

Executa 5 instâncias do `example.php` em paralelo e valida se o DTMF '*' foi processado corretamente.

```bash
php run_parallel_test.php
```

**O que é testado:**
- ✅ Execução paralela de 5 chamadas SIP simultâneas
- ✅ Processamento correto de DTMF (tecla '*')
- ✅ Validação da string "número inválido" na saída
- ✅ Detecção de timeouts e erros
- ✅ Monitoramento de processos em tempo real

**Critério de Sucesso:**
A string `"número inválido"` deve aparecer nos logs de TODAS as 5 instâncias, indicando que:
1. A chamada SIP foi estabelecida com sucesso
2. O DTMF '*' foi enviado corretamente
3. O servidor SIP processou e respondeu ao DTMF
4. A resposta foi recebida e processada corretamente

**Configuração necessária:**

Crie um arquivo `.env` com as credenciais SIP:

```env
SIP_USERNAME=seu_usuario
SIP_PASSWORD=sua_senha
SIP_HOST=servidor.sip.com
DEEPGRAM=sua_chave_deepgram
```

**Timeout:** 60 segundos por instância

**Logs:** Salvos em `tests/integration_logs/`

---

## 📁 Estrutura de Arquivos

```
libspech/
├── run_tests.php                    # Runner para testes RTP
├── run_media_tests.php              # Runner para testes Media
├── run_parallel_test.php            # Teste de integração paralela
├── example.php                      # Exemplo de uso do sistema
├── tests/
│   ├── bootstrap.php                # Bootstrap com mocks
│   ├── RtpChannelTest.php           # Suite de testes RTP (48)
│   ├── MediaChannelTest.php         # Suite de testes Media (51)
│   ├── RtpPacketValidator.php       # Validador RFC 3550
│   ├── DtmfValidator.php            # Validador RFC 2833
│   ├── integration_test.php         # Teste de integração (alternativo)
│   ├── integration_logs/            # Logs dos testes paralelos
│   └── mocks/                       # Mocks de extensões
│       ├── SwooleSocket.php
│       ├── SwooleChannel.php
│       ├── SwooleCoroutine.php
│       └── StringObject.php
└── plugins/
    └── Utils/
        └── sip/
            ├── rtpChannel.php       # Classe testada (48 testes)
            ├── mediaChannel.php     # Classe testada (51 testes)
            ├── DtmfEvent.php
            └── rtpc.php
```

---

## 📊 Resultados

### Resumo Geral

| Suite de Testes | Total | Passou | Falhou | Taxa |
|-----------------|-------|--------|--------|------|
| RTP Channel     | 48    | 48     | 0      | 100% ✅ |
| Media Channel   | 51    | 51     | 0      | 100% ✅ |
| **TOTAL**       | **99**| **99** | **0**  | **100%** ✅ |

### Conformidade RFC

✅ **RFC 3550 (RTP)** - 100% conforme
- Version 2
- Header structure
- Sequence numbering
- Timestamp handling
- SSRC management

✅ **RFC 2833/4733 (DTMF)** - 100% conforme
- Event codes (0-15)
- Marker bit no primeiro pacote
- End bit nos 3 pacotes finais
- Timestamp fixo durante evento
- Duração crescente

---

## 🚀 Execução Rápida

### Todos os testes unitários:
```bash
php run_tests.php && php run_media_tests.php
```

### Teste completo (unitários + integração):
```bash
php run_tests.php && \
php run_media_tests.php && \
php run_parallel_test.php
```

---

## 🐛 Troubleshooting

### Teste paralelo falhando?

1. **Verifique as variáveis de ambiente:**
   ```bash
   cat .env
   ```

2. **Verifique os logs:**
   ```bash
   cat tests/integration_logs/instance_1.log
   cat tests/integration_logs/instance_1.error.log
   ```

3. **Teste uma única instância:**
   ```bash
   php example.php
   ```

4. **Verifique conectividade SIP:**
   - Servidor SIP está acessível?
   - Credenciais estão corretas?
   - Firewall bloqueando portas?

### Mocks não funcionando?

Os mocks são carregados automaticamente pelo `bootstrap.php`. Se houver problemas:

```bash
php -l tests/bootstrap.php
php -l tests/mocks/*.php
```

---

## 📝 Adicionando Novos Testes

### Para adicionar testes unitários:

1. Edite `tests/RtpChannelTest.php` ou `tests/MediaChannelTest.php`
2. Adicione um novo método de teste privado
3. Chame o método em `runAll()`
4. Use `$this->assert()` para validações

Exemplo:
```php
private function testNovoRecurso(): void
{
    echo "📋 Novo Recurso Tests\n";

    $this->assert(function () {
        $channel = new rtpChannel();
        return $channel->novoMetodo() === valorEsperado;
    }, "Descrição do teste");

    echo "\n";
}
```

### Para adicionar testes de integração:

1. Modifique `run_parallel_test.php`
2. Adicione novos patterns em `validateResults()`
3. Ajuste timeout se necessário

---

## ⚡ Performance

- **Testes unitários:** < 0.01s (99 testes)
- **Teste paralelo:** ~30-60s (depende da rede SIP)
- **Total:** < 1 minuto para suite completa

---

## 📞 Suporte

Em caso de dúvidas ou problemas:
1. Verifique os logs em `tests/integration_logs/`
2. Execute os testes unitários primeiro
3. Valide a conectividade SIP manualmente
4. Revise o arquivo `.env`

---

## ✨ Notas

- Todos os testes são idempotentes (podem ser executados múltiplas vezes)
- Os mocks garantem que os testes funcionem sem extensões nativas
- O teste paralelo requer um servidor SIP funcional
- Logs são limpos automaticamente a cada execução

**Última atualização:** 2026-02-24
