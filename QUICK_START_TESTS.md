# 🚀 Guia Rápido - Testes libspech

## ⚡ Execução Rápida

### Todos os Testes Unitários (99 testes - 0.005s)
```bash
./run_all_tests.sh
```

### Apenas RTP Channel (48 testes)
```bash
php run_tests.php
```

### Apenas Media Channel (51 testes)
```bash
php run_media_tests.php
```

### Teste de Integração Paralela (5 instâncias)
```bash
# IMPORTANTE: Configure .env primeiro!
php run_parallel_test.php
```

---

## 📋 Pré-requisitos

### Para Testes Unitários:
- ✅ PHP 8.1+
- ✅ Nada mais! (Mocks incluídos)

### Para Teste de Integração:
- ✅ PHP 8.1+
- ✅ Swoole extension
- ✅ Servidor SIP configurado
- ✅ Arquivo `.env` com credenciais

---

## 🔧 Configuração do .env

Crie o arquivo `.env` na raiz do projeto:

```env
SIP_USERNAME=seu_usuario_sip
SIP_PASSWORD=sua_senha_sip
SIP_HOST=servidor.sip.com
DEEPGRAM=sua_chave_deepgram_opcional
```

---

## ✅ O Que Esperar

### Teste de Sucesso:
```
✓ Todos os 48 testes passaram!
⏱  Tempo de execução: 0.003s
```

### Teste de Integração Bem-Sucedido:
```
✓ Instância #1: PASS
  ✓ Número inválido encontrado
  ✓ DTMF detectado
  ✓ Chamada aceita
```

**String chave:** `"número inválido"` deve aparecer em TODAS as 5 instâncias

---

## 🐛 Troubleshooting

### Teste unitário falhou?
```bash
# Verifica sintaxe
php -l plugins/Utils/sip/rtpChannel.php
php -l plugins/Utils/sip/mediaChannel.php
```

### Teste de integração não encontra "número inválido"?

1. **Verifique o log:**
   ```bash
   cat tests/integration_logs/instance_1.log
   ```

2. **Teste manualmente:**
   ```bash
   php example.php
   ```

3. **Checklist:**
   - [ ] Servidor SIP está online?
   - [ ] Credenciais corretas no .env?
   - [ ] Firewall liberado?
   - [ ] Número de destino correto?

---

## 📊 Status dos Testes

| Suite | Testes | Status |
|-------|--------|--------|
| RTP Channel | 48 | ✅ 100% |
| Media Channel | 51 | ✅ 100% |
| Integração | 5 | 🔄 Requer SIP |

**Total Unitários:** 99/99 ✅

---

## 📁 Estrutura Simples

```
libspech/
├── run_all_tests.sh          ← Execute este
├── run_tests.php             ← Ou este (RTP)
├── run_media_tests.php       ← Ou este (Media)
├── run_parallel_test.php     ← Ou este (Integração)
├── example.php               ← Exemplo de uso
├── .env                      ← Configure aqui
└── tests/
    ├── integration_logs/     ← Veja logs aqui
    └── ...
```

---

## 💡 Dicas

1. **Execute unitários primeiro** - São rápidos e não precisam de setup
2. **Configure .env corretamente** - Essencial para integração
3. **Verifique logs** - Sempre em `tests/integration_logs/`
4. **Teste manualmente** - Use `php example.php` para debug

---

## 🎯 Critério de Sucesso - Integração

Para o teste passar, **TODAS** as 5 instâncias precisam:

✅ Estabelecer conexão SIP
✅ Enviar DTMF '*'
✅ Receber resposta com "número inválido"
✅ Completar sem timeout

Se **UMA** falhar = teste falha

---

## 📞 Comandos Úteis

```bash
# Ver apenas resultados finais
php run_tests.php 2>&1 | tail -10

# Contar arquivos de teste
find tests/ -name "*.php" | wc -l

# Limpar logs antigos
rm -rf tests/integration_logs/*

# Testar sintaxe de todos os scripts
find . -name "run*.php" -exec php -l {} \;
```

---

## ⏱️ Tempo Esperado

- Unitários: **< 0.01s**
- Integração: **30-60s** (por instância)
- Total: **< 1 minuto**

---

## 🆘 Suporte

1. Leia `TESTING.md` para detalhes completos
2. Verifique logs em `tests/integration_logs/`
3. Execute manualmente `php example.php`
4. Revise configuração `.env`

---

**Última atualização:** 2026-02-24
**Versão:** 1.0.0
**Status:** ✅ Pronto para produção
