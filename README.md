# libspech

[![PHP Version](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://www.php.net/)
[![Swoole](https://img.shields.io/badge/Swoole-6.0+-green.svg)](https://www.swoole.com/)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Biblioteca PHP completa para comunicação VoIP em tempo real via SIP/RTP**

Uma implementação robusta e performática do protocolo SIP com suporte total a streaming de áudio RTP/RTCP, construída
com corrotinas Swoole. Realize chamadas VoIP reais com transmissão e recepção de áudio bidirecional diretamente do PHP.

## Características Principais

- ✅ **Chamadas VoIP bidirecionais** - Transmissão e recepção simultânea de áudio em tempo real
- ✅ **Streaming RTP/RTCP** - Protocolo de transporte de mídia com controle de qualidade
- ✅ **Múltiplos codecs** - PCMU, PCMA, G.729, Opus, L16 com conversão automática
- ✅ **Registro SIP** - Autenticação MD5 Digest completa
- ✅ **Eventos assíncronos** - Callbacks para ringing, answer, hangup, receive audio
- ✅ **Alta performance** - Assíncrono e não-bloqueante com Swoole
- ✅ **DTMF (RFC 2833)** - Envio de tons de teclado telefônico
- ✅ **Gravação de áudio** - Captura em formato WAV

## Índice

- [Instalação](#instalação)
- [Início Rápido](#início-rápido)
- [Casos de Uso](#casos-de-uso)
- [Codecs Suportados](#codecs-suportados)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [API e Eventos](#api-e-eventos)
- [Exemplos Avançados](#exemplos-avançados)
- [Limitações](#limitações)
- [Contribuindo](#contribuindo)
- [Licença](#licença)

## Instalação

### Requisitos

| Componente   | Versão | Descrição                          |
|--------------|--------|------------------------------------|
| **PHP**      | 8.4+   | Linguagem principal                |
| **Swoole**   | 6.0+   | Framework assíncrono (obrigatório) |
| **bcg729**   | 1.0+   | Codec G.729 (opcional)             |
| **opus**     | 1.0+   | Codec Opus (opcional)              |
| **psampler** | 1.0+   | Resampling de áudio (opcional)     |

### Instalação Rápida

```bash
# 1. Clonar repositório
git clone https://github.com/berzersks/libspech.git
cd libspech

# 2. Instalar Swoole (obrigatório)
pecl install swoole

# 3. Verificar instalação
php -m | grep swoole
```

### Extensões Opcionais

<details>
<summary><b>bcg729</b> - Codec G.729</summary>

```bash
git clone https://github.com/berzersks/bcg729.git
cd bcg729
phpize && ./configure && make && sudo make install
echo "extension=bcg729.so" >> /etc/php/8.4/cli/php.ini
```

</details>

<details>
<summary><b>opus</b> - Codec Opus</summary>

```bash
git clone https://github.com/berzersks/opus.git
cd opus
phpize && ./configure && make && sudo make install
echo "extension=opus.so" >> /etc/php/8.4/cli/php.ini
```

</details>

<details>
<summary><b>psampler</b> - Audio Resampling</summary>

```bash
git clone https://github.com/berzersks/psampler.git
cd psampler
phpize && ./configure && make && sudo make install
echo "extension=psampler.so" >> /etc/php/8.4/cli/php.ini
```

</details>

## Início Rápido

### Exemplo Básico

```php
<?php
include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    // Configurar credenciais SIP
    $phone = new trunkController(
        'your_username',
        'your_password',
        'sip.example.com',
        5060
    );

    // Registrar no servidor SIP
    if (!$phone->register(2)) {
        throw new \Exception("Falha no registro");
    }

    // Configurar eventos
    $phone->onRinging(fn() => echo "Chamando...\n");

    $phone->onAnswer(function (trunkController $phone) {
        echo "Atendido!\n";
        $phone->receiveMedia();

        // Encerrar após 10 segundos
        \Swoole\Coroutine::sleep(10);
        $phone->socket->sendto($phone->host, $phone->port, sip::renderSolution(
            \handlers\renderMessages::generateBye($phone->headers200['headers'])
        ));
    });

    $phone->onHangup(function (trunkController $phone) {
        echo "Chamada encerrada\n";
        $phone->saveBufferToWavFile('gravacao.wav', $phone->bufferAudio);
    });

    $phone->onReceiveAudio(function ($pcm, $peer, trunkController $phone) {
        $phone->bufferAudio .= $pcm;
    });

    // Realizar chamada
    $phone->call('5511999999999');
});
```

### Executar

```bash
# Edite example.php com suas credenciais
php example.php
```

## Casos de Uso

- 🤖 **Bots de voz automatizados** - IVR (URA), assistentes virtuais
- 📞 **Softphones em PHP** - Aplicações de telefonia integradas
- 🎙️ **Gravação de chamadas** - Captura e processamento de áudio em tempo real
- 🔊 **Análise de voz** - Processamento para transcrição ou análise
- 🔗 **Integração VoIP** - Conectar aplicações PHP a infraestrutura VoIP existente
- 🧪 **Testes automatizados** - Simulação de chamadas e validação de sistemas

## Estrutura do Projeto

```
libspech/
├── plugins/
│   ├── Utils/sip/              # Core SIP/RTP
│   │   ├── trunkController.php # Controlador principal
│   │   ├── phone.php           # Gerenciamento de estados
│   │   ├── sip.php             # Parser SIP
│   │   ├── rtpChannels.php     # Transmissão RTP
│   │   └── rtpc.php            # Recepção RTP
│   ├── Packet/controller/      # Mensagens SIP/SDP
│   └── Utils/{cache,cli,network}/
├── stubs/                      # Stubs para IDE
└── example.php                 # Exemplo funcional
```

### Componentes Principais

| Arquivo               | Responsabilidade                               |
|-----------------------|------------------------------------------------|
| `trunkController.php` | Registro SIP e gerenciamento de chamadas       |
| `phone.php`           | Estados de chamada (ringing, answered, hangup) |
| `sip.php`             | Parser/render de mensagens SIP e SDP           |
| `rtpChannels.php`     | Criação e envio de pacotes RTP                 |
| `rtpc.php`            | Recepção e decodificação de pacotes RTP        |

## Codecs Suportados

| Codec                  | PT  | Taxa  | Status     | Extensão                                          |
|------------------------|-----|-------|------------|---------------------------------------------------|
| **PCMU (G.711 μ-law)** | 0   | 8kHz  | ✅ Completo | Nativa                                            |
| **PCMA (G.711 A-law)** | 8   | 8kHz  | ✅ Completo | Nativa                                            |
| **G.729**              | 18  | 8kHz  | ✅ Completo | [bcg729](https://github.com/berzersks/bcg729)     |
| **Opus**               | 111 | 48kHz | 🚧 Beta    | [opus](https://github.com/berzersks/opus)         |
| **L16**                | 96  | 8kHz  | ✅ Completo | [psampler](https://github.com/berzersks/psampler) |
| **telephone-event**    | 101 | 8kHz  | ✅ DTMF     | Nativa                                            |

### Configurar Codec

```php
// Configurar codec específico
$phone->mountLineCodecSDP('opus/48000');  // Opus
$phone->mountLineCodecSDP('L16/8000');    // PCM linear
$phone->mountLineCodecSDP('G729/8000');   // G.729

// Padrão: PCMU/PCMA (automático)
```

## API e Eventos

### Métodos Principais

```php
// Registro e chamadas
$phone->register(int $expires): bool
$phone->call(string $number): void

// Eventos
$phone->onRinging(callable $callback): void
$phone->onAnswer(callable $callback): void
$phone->onHangup(callable $callback): void
$phone->onReceiveAudio(callable $callback): void

// Mídia
$phone->receiveMedia(): void
$phone->send2833(string $digit): void
$phone->saveBufferToWavFile(string $filename, string $pcmData): void
```

### Fluxo de Chamada

```
1. REGISTRO
   PHP → [REGISTER] → SIP Server → [200 OK] → PHP

2. CHAMADA
   PHP → [INVITE+SDP] → SIP Server → [180 Ringing] → PHP (onRinging)
       ← [200 OK+SDP] ←            → [ACK] →

3. MÍDIA RTP (Bidirecional)
   PHP ⇄ [RTP Packets] ⇄ Destino (onReceiveAudio)

4. ENCERRAMENTO
   PHP → [BYE] → Destino → [200 OK] → PHP (onHangup)
```

## Exemplos Avançados

<details>
<summary><b>Exemplo 1: Usar Opus para Chamadas</b></summary>

```php
<?php
include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    $phone = new trunkController('user', 'pass', 'sip.example.com', 5060);
    $phone->register(2);

    // Criar codec Opus
    $opus = new opusChannel(48000, 1);
    $opus->setBitrate(24000);
    $opus->setVBR(true);
    $opus->setDTX(true);

    $phone->mountLineCodecSDP('opus/48000');

    $phone->onReceiveAudio(function ($pcm, $peer, $phone) use ($opus) {
        // Melhorar clareza da voz
        $enhanced = $opus->enhanceVoiceClarity($pcm, 0.7);
        $phone->bufferAudio .= $enhanced;
    });

    $phone->call('5511999999999');
});
```

</details>

<details>
<summary><b>Exemplo 2: Transcodificar Áudio</b></summary>

```php
<?php
// PCMU → PCM → Opus
$pcmuData = file_get_contents('audio.pcmu');
$pcm = decodePcmuToPcm($pcmuData);
$pcm48k = resampler($pcm, 8000, 48000);

$opus = new opusChannel(48000, 1);
$opusData = $opus->encode($pcm48k);
file_put_contents('audio.opus', $opusData);
```

</details>

<details>
<summary><b>Exemplo 3: G.729 Alta Compressão</b></summary>

```php
<?php
$g729 = new bcg729Channel();
$pcm = file_get_contents('audio.pcm');
$g729Data = $g729->encode($pcm);

echo "Compressão: " . round((1 - strlen($g729Data)/strlen($pcm)) * 100) . "%\n";
```

</details>

## Limitações

| Item                      | Status                 |
|---------------------------|------------------------|
| IPv6                      | ❌ Não suportado        |
| SRTP/TLS                  | ❌ Sem criptografia     |
| Chamadas de entrada       | ❌ Apenas saída         |
| Transcodificação dinâmica | ❌ Um codec por chamada |

## Contribuindo

Contribuições são bem-vindas!

1. Fork o repositório
2. Crie uma branch (`git checkout -b feature/minha-feature`)
3. Commit suas mudanças (`git commit -m 'Add: nova funcionalidade'`)
4. Push para a branch (`git push origin feature/minha-feature`)
5. Abra um Pull Request

## Licença

Este projeto está licenciado sob a **MIT License** - veja o arquivo [LICENSE](LICENSE) para detalhes.

### Dependências de Terceiros

| Projeto    | Licença    | Link                                               |
|------------|------------|----------------------------------------------------|
| **Swoole** | Apache 2.0 | https://github.com/swoole/swoole-src               |
| **bcg729** | GPL-3.0    | https://github.com/BelledonneCommunications/bcg729 |
| **Opus**   | BSD        | https://opus-codec.org/                            |

⚠️ **Nota**: As extensões PHP (bcg729, opus, psampler) mantêm suas próprias licenças. Consulte os repositórios
individuais.

## Roadmap

- [ ] Chamadas de entrada (servidor SIP)
- [ ] SRTP/TLS para segurança
- [ ] Suporte IPv6
- [ ] Framework de testes (PHPUnit)
- [ ] Suporte G.722 wideband
- [ ] Documentação API completa

## Suporte

- 🐛 **Issues**: [GitHub Issues](https://github.com/berzersks/libspech/issues)
- 💬 **Discussões**: [GitHub Discussions](https://github.com/berzersks/libspech/discussions)

---

## Créditos

**Desenvolvido por**: [berzersks](https://github.com/berzersks)

**Agradecimentos**: Swoole Team, Belledonne Communications, Xiph.Org Foundation, IETF, Comunidade PHP VoIP

**Tecnologias**: PHP 8.4+ | Swoole 6.0+ | SIP (RFC 3261) | RTP/RTCP (RFC 3550) | SDP (RFC 4566)

---

**Desenvolvido para a comunidade PHP VoIP** 🚀
