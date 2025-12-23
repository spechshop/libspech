# libspech

[![PHP Version](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://www.php.net/)
[![Swoole](https://img.shields.io/badge/Swoole-6.0+-green.svg)](https://www.swoole.com/)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](LICENSE.txt)
[![Website](https://img.shields.io/badge/Website-spechshop.com-orange.svg)](https://spechshop.com)

Biblioteca VoIP SIP/RTP em tempo real para PHP, construída com corrotinas Swoole. Faça e receba chamadas telefônicas de PHP, transmita
audio RTP, manipule DTMF e grave áudio.

> **📖 OPEN SOURCE** - Copyright © 2025 Lotus / berzersks
> Licensed under Apache 2.0. Free to use, modify, and distribute.
> **Please respect the creator and contribute at the [official repository](https://github.com/spechshop/libspech)**

## Visão Geral

libspech fornece:

- Recursos de user-agent SIP: registro, configuração/desmontagem de chamadas (INVITE/200/ACK/BYE), autenticação digest
- Canais de mídia RTP/RTCP: receber e enviar quadros de áudio
- API orientada a eventos com callbacks para toque, resposta, desligamento e áudio recebido
- Envio de DTMF (RFC 2833)
- Auxiliares de gravação WAV para PCM capturado
- I/O assíncrono de alto desempenho via Swoole

Este README reflete o repositório a partir de 2025-11-24.

## Stack

- Linguagem: PHP (sem Composer neste repositório)
- Framework/runtime: Corrotinas Swoole (incluído nas releases pcg729)
- Protocolos: SIP, RTP/RTCP, SDP, DTMF (RFC 2833)
- Extensões nativas: `bcg729`, `opus`, `psampler` (incluídas nas releases pcg729)

## Requisitos

- Linux/macOS recomendado
- Releases do [berzersks/pcg729](https://github.com/berzersks/pcg729/releases) que incluem PHP 8.4+ com Swoole, bcg729 (baseado no Belladone BCG729), Opus e psampler pré-compilados

## Instalação

Baixe a última release do [berzersks/pcg729](https://github.com/berzersks/pcg729/releases). Esta release inclui todas as extensões necessárias (Swoole, bcg729 baseado no Belladone BCG729, Opus, psampler) pré-compiladas e prontas para uso.

Siga as instruções de instalação fornecidas na release para configurar o ambiente.

## Começando

O repositório inclui um exemplo executável em `example.php`.

Exemplo mínimo:

```php
<?php
use libspech\Sip\trunkController;

include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    $username = 'seu_username';
    $password = 'sua_password';
    $domain   = 'sip.example.com';
    $host     = gethostbyname($domain);

    $phone = new trunkController($username, $password, $host, 5060);

    if (!$phone->register(2)) {
        throw new \Exception('Falha no registro');
    }

    // Oferecer uma linha PCM linear em SDP (opcional)
    $phone->mountLineCodecSDP('L16/8000');

    $phone->onRinging(function () {
        echo "Tocando...\n";
    });

    $phone->onAnswer(function (trunkController $phone) {
        echo "Atendido. Recebendo mídia...\n";
        $phone->receiveMedia();
        \Swoole\Coroutine::sleep(10);
        // Desligar depois de um tempo (BYE)
        // Veja example.php para um envio completo de BYE usando sip/renderMessages
    });

    $phone->onReceiveAudio(function ($pcmData, $peer, trunkController $phone) {
        $phone->bufferAudio .= $pcmData; // capturar PCM bruto
    });

    $phone->onHangup(function (trunkController $phone) {
        $phone->saveBufferToWavFile('audio.wav', $phone->bufferAudio);
        echo "Salvou audio.wav\n";
    });

    $phone->call('5511999999999');
});
```

Executar:

```bash
php example.php
```

## Scripts

- Não há gerenciador de pacotes ou executor de scripts neste repositório. Use o PHP CLI diretamente.
- Ponto de entrada para a demo é `example.php`.

## Variáveis de Ambiente

- Nenhuma variável de ambiente fixa é necessária pela biblioteca conforme commitado.
- TODO: documentar qualquer configuração de runtime que deve ser externalizada (ex.: credenciais SIP, proxies, IP público/NAT).

## Estrutura do Projeto

```
libspech/
├── example.php
├── plugins/
│   ├── autoloader.php                 # Autoloader simples orientado por configInterface.json
│   ├── configInterface.json           # Lista diretórios de autoload
│   ├── Packet/
│   │   └── controller/
│   │       └── renderMessages.php     # Auxiliares de renderização de mensagens SIP/SDP
│   └── Utils/
│       ├── cache/
│       │   ├── cache.php
│       │   └── rpcClient.php
│       ├── cli/cli.php                # Utilitários auxiliares CLI
│       ├── libspech/trunkController.php  # Controlador principal de chamadas (namespace libspech\\Sip)
│       ├── network/network.php
│       └── sip/
│           ├── AdaptiveBuffer.php
│           ├── DtmfEvent.php
│           ├── mediaChannel.php
│           ├── rtpChannel.php
│           ├── rtpc.php
│           ├── sip.php
│           └── trunkController.php    # Controlador legado/alt (mantido para compatibilidade)
├── stubs/                             # Stubs IDE para extensões opcionais
│   ├── bcg729Channel.php
│   ├── opusChannel.php
│   └── psampler.php
├── LICENSE.txt
├── README.md
└── SECURITY.md
```

Nota sobre namespaces: a classe definida em `plugins/Utils/libspech/trunkController.php` usa o namespace `libspech\Sip`.
Use statements no código devem direcionar `libspech\Sip\trunkController` como mostrado no exemplo.

## Codecs

Payloads suportados/disponíveis no codebase:

| Codec                  | Tipo de Payload | Taxa de Amostragem | Status   | Notas/Extensão                                  |
|------------------------|-----------------|---------------------|----------|-------------------------------------------------|
| PCMU (G.711 µ-law)     | 0               | 8 kHz               | Integrado | Nenhuma extensão extra necessária               |
| PCMA (G.711 A-law)     | 8               | 8 kHz               | Integrado | Nenhuma extensão extra necessária               |
| G.729                  | 18              | 8 kHz               | Integrado | Incluído na release pcg729 (baseado no Belladone BCG729) |
| Opus                   | 111             | 48 kHz              | Integrado | Incluído na release pcg729                      |
| L16 (Linear PCM)       | 96              | 8 kHz               | Integrado | psampler incluído para reamostragem            |
| telephone-event (DTMF) | 101             | 8 kHz               | Integrado | RFC 2833 para sinalização DTMF                 |

Notas:

- Múltiplos codecs podem ser oferecidos via SDP. Use `mountLineCodecSDP()` para ajustar preferências.
- Alguns valores de tipo de payload podem variar dependendo da negociação; verifique com seu provedor.

## Notas de Uso

- Rede/NAT: certifique-se de que o IP local e portas que a biblioteca vincula sejam alcançáveis pelo peer SIP. STUN/travessia NAT
  não está incluída. TODO: documentar utilitários auxiliares ou melhores práticas para ambientes NAT.
- Segurança: esta biblioteca foca no SIP básico sobre UDP. TLS/SRTP não estão documentados aqui. TODO: esclarecer status de suporte TLS/SRTP.

## Testes

- Não há testes automatizados no repositório no momento.
- TODO: adicionar testes unitários/integração para parsing de mensagens SIP, timing RTP, DTMF e fluxos de chamadas de exemplo.

## Licença

This project is licensed under the **Apache License 2.0**.

**Copyright © 2025 Lotus / berzersks**
**Website: [https://spechshop.com](https://spechshop.com)**
**Official Repository: [https://github.com/spechshop/libspech](https://github.com/spechshop/libspech)**

### Important Notice to the Community

This is **open source software**. You are free to use, modify, and distribute it under the Apache 2.0 license. However, we kindly ask that you:

- ✅ **Respect the creator**: Maintain attribution to Lotus (berzersks) in all derivative works
- ✅ **Keep copyright notices**: Do not remove or alter copyright notices and attributions
- ✅ **Unite the community**: Consider contributing improvements to the official repository rather than creating fragmented forks
- ✅ **Submit pull requests**: Help make this project better for everyone by contributing at [github.com/spechshop/libspech](https://github.com/spechshop/libspech)
- ✅ **Reference the creator**: Credit the original author when discussing or referencing this software

A unified community is stronger and advances faster together. Thank you for helping build a respectful and collaborative open source project!

See [LICENSE.txt](LICENSE.txt) for full license terms.

### Third-Party Dependencies

Third-party components are under their respective licenses:
- Swoole: Apache License 2.0
- bcg729: GNU GPL v3.0
- Opus: BSD License
- psampler: See repository for details

Review their license files before use in production.
