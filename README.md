# libspech

**Biblioteca PHP completa para comunicação de voz real via SIP/RTP**

Uma implementação robusta do protocolo SIP (Session Initiation Protocol) com suporte total a streaming de áudio RTP/RTCP
em tempo real, construída com corrotinas Swoole para alto desempenho. Esta biblioteca permite que aplicações PHP
realizem chamadas VoIP reais com transmissão e recepção de áudio bidirecional.

## Visão Geral

**libspech** é uma biblioteca SIP funcional e completa que fornece:

### Comunicação de Voz Real

- **Chamadas VoIP bidirecionais completas** - Transmissão e recepção simultânea de áudio
- **Streaming RTP em tempo real** - Envio e recebimento de pacotes de áudio via protocolo RTP
- **Suporte a múltiplos codecs** - PCMU (G.711 μ-law), PCMA (G.711 A-law), G.729
- **Monitoramento RTCP** - Controle de qualidade e sincronização de mídia
- **Conversão de codecs** - Encoding/decoding entre PCM, PCMA e PCMU

### Funcionalidades SIP

- **Registro SIP com autenticação** - Suporte completo a MD5 Digest Authentication
- **Gerenciamento de chamadas** - INVITE, ACK, BYE, CANCEL
- **Negociação SDP** - Session Description Protocol para configuração de mídia
- **Arquitetura orientada a eventos** - Callbacks assíncronos para todos os estados de chamada

### Características Técnicas

- **Assíncrono e não-bloqueante** - Baseado em corrotinas Swoole
- **Alta performance** - Processamento eficiente de pacotes UDP
- **Baixa latência** - Streaming direto de áudio sem buffers desnecessários
- **Código limpo e enxuto** - Apenas 11 arquivos essenciais, sem dependências complexas

## Requisitos

- **PHP**: 8.4+ (testado em PHP 8.4.13)
- **Extensões**:
  - Swoole 6.0+ (para corrotinas e rede assíncrona)
  - bcg729 (opcional, para suporte ao codec G.729)
- **Rede**: Acesso à porta UDP para SIP (padrão 5060) e portas RTP
- **Sistema Operacional**: Linux (recomendado), macOS, Windows com WSL

## Instalação

### Clonar o Repositório

```bash
git clone https://github.com/berzersks/libspech.git
cd libspech
```

### Instalar Dependências

O projeto usa um **autoloader personalizado** configurado em `plugins/configInterface.json`. Não há dependências via
Composer.

Certifique-se de que a extensão Swoole esteja instalada:

```bash
# Instalar Swoole via PECL
pecl install swoole

# Ou compilar do código fonte
# Veja: https://github.com/swoole/swoole-src

# Verificar instalação
php -m | grep swoole
```

### Instalar Extensões de Áudio (Opcional)

Para suporte a codecs adicionais:

**bcg729** (Codec G.729):

```bash
git clone https://github.com/berzersks/bcg729.git
cd bcg729
phpize
./configure
make
sudo make install
```

**opus** (Codec Opus):

```bash
git clone https://github.com/berzersks/opus.git
cd opus
phpize
./configure
make
sudo make install
```

**psampler** (Audio sampling utilities):

```bash
git clone https://github.com/berzersks/psampler.git
cd psampler
phpize
./configure
make
sudo make install
```

Adicione as extensões ao seu `php.ini`:

```ini
extension = bcg729.so
extension = opus.so
extension = psampler.so
```

## Uso

### Exemplo Básico

Veja `example.php` para um exemplo completo e funcional:

```php
<?php

include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    // Configure SIP credentials
    $username = 'your_sip_username';
    $password = 'your_sip_password';
    $domain = 'your_sip_domain.com';
    $host = gethostbyname($domain);

    // Create trunk controller
    $phone = new trunkController(
        $username,
        $password,
        $host,
        5060,
    );

    // Register with SIP server
    if (!$phone->register(2)) {
        throw new \Exception("Registration failed");
    }

    // Configure codec (optional - default is PCMU/PCMA)
    $phone->mountLineCodecSDP('L16/8000');

    // Set up event handlers
    $phone->onRinging(function ($call) {
        \Plugin\Utils\cli::pcl("Call is ringing...", "yellow");
    });

    $phone->onAnswer(function (trunkController $phone) {
        $phone->receiveMedia();
        \Plugin\Utils\cli::pcl("Call answered!", "green");

        // Send DTMF tone (optional)
        \Swoole\Coroutine::sleep(2);
        $phone->send2833('*');

        // Hangup after 10 seconds
        \Swoole\Coroutine::sleep(10);
        $phone->socket->sendto($phone->host, $phone->port, sip::renderSolution(
            \handlers\renderMessages::generateBye($phone->headers200['headers'])
        ));
    });

    $phone->onHangup(function (trunkController $phone) {
        \Plugin\Utils\cli::pcl("Call ended", "red");

        // Save recorded audio to WAV file
        $phone->saveBufferToWavFile('audio.wav', $phone->bufferAudio);
    });

    $phone->onReceiveAudio(function ($pcmData, $peer, trunkController $phone) {
        // Buffer received audio
        $phone->bufferAudio .= $pcmData;
    });

    // Make a call with prefix
    $phone->prefix = 4479;
    $phone->call('551140040104');
});
```

## Estrutura do Projeto

```
libspech/
├── plugins/                          # Módulos principais da biblioteca
│   ├── autoloader.php               # Autoloader customizado
│   ├── configInterface.json         # Configuração do autoloader
│   │
│   ├── Packet/                      # Manipuladores de pacotes SIP
│   │   └── controller/
│   │       └── renderMessages.php   # Renderização de mensagens SIP/SDP
│   │
│   └── Utils/                       # Classes utilitárias
│       │
│       ├── cache/                   # Sistema de cache global
│       │   ├── cache.php            # Cache para estado da aplicação
│       │   └── rpcClient.php        # Cliente RPC para comunicação
│       │
│       ├── cli/                     # Interface de linha de comando
│       │   └── cli.php              # Output colorido no console
│       │
│       ├── network/                 # Utilitários de rede
│       │   └── network.php          # Resolução de IP e gerenciamento de portas
│       │
│       └── sip/                     # Core SIP/RTP (comunicação de voz)
│           ├── trunkController.php  # Controlador principal - Gerencia registro SIP e chamadas
│           ├── phone.php            # Gerenciamento de telefone e estados de chamada
│           ├── sip.php              # Parser e renderizador de mensagens SIP
│           ├── rtpChannels.php      # Criação e envio de pacotes RTP (áudio)
│           └── rtpc.php             # Parser de pacotes RTP/RTCP recebidos
│
├── stubs/                           # Stubs PHP para autocomplete de IDE
│   ├── bcg729Channel.php            # Interface para codec G.729
│   ├── opusChannel.php              # Interface para codec Opus
│   └── psampler.php                 # Interface para resampling de áudio
│
├── example.php                      # Exemplo funcional de uso
└── README.md                        # Este arquivo
```

### Componentes Principais

| Componente              | Responsabilidade          | Função na Comunicação de Voz                                |
|-------------------------|---------------------------|-------------------------------------------------------------|
| **trunkController.php** | Controlador SIP principal | Registro no servidor SIP, criação/gerenciamento de chamadas |
| **phone.php**           | Gerenciamento de chamadas | Controle de estados (ringing, answered, hangup)             |
| **sip.php**             | Protocolo SIP             | Parser/render de mensagens SIP e SDP                        |
| **rtpChannels.php**     | Transmissão de áudio      | Criação e envio de pacotes RTP com áudio codificado         |
| **rtpc.php**            | Recepção de áudio         | Parsing de pacotes RTP recebidos e extração de áudio        |
| **renderMessages.php**  | Mensagens SIP             | Renderização de respostas SIP (200 OK, ACK, etc.)           |
| **network.php**         | Rede                      | Resolução de IPs e gerenciamento de portas UDP              |
| **cache.php**           | Estado global             | Cache de sessões e dados temporários                        |

## Scripts

### Executar Exemplo

```bash
# Execute o exemplo básico (certifique-se de configurar suas credenciais SIP primeiro)
php example.php
```

**Importante**: Edite `example.php` e configure suas credenciais SIP reais antes de executar:

- `$username`: Seu nome de usuário SIP
- `$password`: Sua senha SIP
- `$domain`: Domínio do servidor SIP
- Número de telefone de destino na linha `$phone->call('...')`

## Configuração

### Configuração do Autoloader

O autoloader é configurado via `plugins/configInterface.json`:

```json
{
  "autoload": [
    "Utils/cache",
    "Utils/cli",
    "Utils/sip",
    "Utils/network",
    "Packet/controller"
  ],
  "reloadCaseFileModify": []
}
```

### Variáveis de Ambiente

Atualmente, nenhuma variável de ambiente é necessária. Toda a configuração é feita programaticamente através do
construtor da classe `trunkController`:

```php
$phone = new trunkController(
    $username,  // Nome de usuário SIP
    $password,  // Senha SIP
    $host,      // Endereço IP do servidor SIP
    $port       // Porta SIP (padrão: 5060)
);
```

**Configurações adicionais**:

- `$phone->prefix`: Prefixo de discagem (opcional)
- `$phone->mountLineCodecSDP()`: Configurar codec preferido
- `$phone->connectTimeout`: Timeout de conexão em segundos (padrão: 30)

## Codecs de Áudio Suportados

A biblioteca oferece suporte completo aos seguintes codecs com **conversão automática**:

| Codec                  | Payload Type | Taxa de Amostragem | Descrição                                  | Status             | Extensão                                          |
|------------------------|--------------|--------------------|--------------------------------------------|--------------------|---------------------------------------------------|
| **PCMU (G.711 μ-law)** | 0            | 8000 Hz            | Codec padrão para América do Norte e Japão | Completo           | Nativa                                            |
| **PCMA (G.711 A-law)** | 8            | 8000 Hz            | Codec padrão para Europa e resto do mundo  | Completo           | Nativa                                            |
| **G.729**              | 18           | 8000 Hz            | Codec comprimido de alta qualidade         | Completo           | [bcg729](https://github.com/berzersks/bcg729)     |
| **Opus**               | 111          | 48000 Hz           | Codec de alta qualidade e baixa latência   | Em desenvolvimento | [opus](https://github.com/berzersks/opus)         |
| **L16**                | 96           | 8000 Hz            | PCM linear 16-bit                          | Completo           | [psampler](https://github.com/berzersks/psampler) |
| **telephone-event**    | 101          | 8000 Hz            | Eventos DTMF (tons de teclado)             | Suportado          | Nativa                                            |

### Conversão de Codecs

A biblioteca inclui funções nativas e extensões para conversão de áudio:

**Funções Nativas** (built-in):

- `encodePcmToPcma()` / `decodePcmaToPcm()` - PCM ↔ A-law
- `encodePcmToPcmu()` / `decodePcmuToPcm()` - PCM ↔ μ-law
- `linear2alaw()` / `alaw2linear()` - Conversão linear para A-law
- `linear2ulaw()` / `ulaw2linear()` - Conversão linear para μ-law

**Extensão bcg729**:

- `decodePcmaToPcm(string $input): string` - PCMA para PCM
- `decodePcmuToPcm(string $input): string` - PCMU para PCM
- `pcmLeToBe(string $input): string` - Little-endian para Big-endian

## Casos de Uso

Esta biblioteca é ideal para:

- **Bots de voz automatizados** - IVR (URA), assistentes virtuais
- **Softphones em PHP** - Aplicações de telefonia integradas
- **Gravação de chamadas** - Captura e processamento de áudio em tempo real
- **Análise de voz** - Processamento de áudio para transcrição ou análise
- **Integração com sistemas existentes** - Conectar aplicações PHP a infraestrutura VoIP
- **Testes de sistemas VoIP** - Simulação de chamadas e testes automatizados

## Como Funciona

### Fluxo de Comunicação VoIP

```
1. REGISTRO SIP
   PHP App → [REGISTER] → Servidor SIP
   PHP App ← [401 Unauthorized] ← Servidor SIP
   PHP App → [REGISTER + Auth] → Servidor SIP
   PHP App ← [200 OK] ← Servidor SIP

2. CHAMADA SAINTE (INVITE)
   PHP App → [INVITE + SDP] → Servidor SIP → Destino
   PHP App ← [100 Trying] ← Servidor SIP
   PHP App ← [180 Ringing] ← Destino (evento: onRinging)
   PHP App ← [200 OK + SDP] ← Destino (evento: onAnswer)
   PHP App → [ACK] → Destino

3. STREAMING DE ÁUDIO RTP (Bidirecional)
   PHP App ⇄ [Pacotes RTP UDP] ⇄ Destino
   - Envio: rtpChannels.php cria pacotes RTP com áudio
   - Recepção: rtpc.php parseia pacotes RTP recebidos
   - Evento: onReceiveAudio() chamado para cada pacote recebido

4. ENCERRAMENTO (BYE)
   PHP App → [BYE] → Destino
   PHP App ← [200 OK] ← Destino (evento: onHangup)
```

## Funcionalidades

### Protocolo SIP

- Registro SIP com autenticação MD5 Digest
- Suporte a métodos: REGISTER, INVITE, ACK, BYE, CANCEL
- Parsing completo de mensagens SIP
- Negociação SDP (Session Description Protocol)
- Gerenciamento de Call-ID, tags, branches

### Mídia RTP/RTCP

- **Transmissão RTP** - Envio de pacotes de áudio em tempo real
- **Recepção RTP** - Parsing e decodificação de áudio recebido
- **RTCP** - Relatórios de qualidade e sincronização
- **Múltiplos codecs** - PCMU, PCMA, G.729
- **Conversão de codecs** - Encoding/decoding automático

### Eventos e Callbacks

- `onRinging()` - Chamada tocando no destino
- `onAnswer(trunkController $phone)` - Chamada atendida
- `onHangup(trunkController $phone)` - Chamada encerrada
- `onReceiveAudio($pcmData, $peer, trunkController $phone)` - Áudio recebido em tempo real

### Funcionalidades Adicionais

- `send2833($digit)` - Envio de tons DTMF (RFC 2833)
- `saveBufferToWavFile($filename, $pcmData)` - Salvar áudio em arquivo WAV
- `receiveMedia()` - Iniciar recepção de mídia RTP
- `bufferAudio` - Buffer interno para armazenar áudio recebido

## Limitações Conhecidas

- **Apenas IPv4** - Suporte a IPv6 não implementado
- **Sem SRTP/TLS** - Comunicação não criptografada
- **Chamadas de saída apenas** - Recepção de chamadas (servidor) não implementada nesta versão
- **Um codec por chamada** - Sem transcodificação dinâmica durante a chamada

## Testes

Framework de teste ainda não implementado. Para testar a biblioteca:

```bash
# 1. Configure suas credenciais SIP em example.php
# 2. Execute o exemplo
php example.php

# Você deve ver a saída:
# - Call is ringing...
# - Call answered!
# - Call ended
# - Arquivo audio.wav gerado com o áudio gravado
```

### TODO: Framework de Testes

- [ ] Adicionar PHPUnit para testes unitários
- [ ] Testes de integração com servidor SIP mock
- [ ] Testes de codecs de áudio
- [ ] Testes de parsing SIP/SDP
- [ ] Testes de pacotes RTP/RTCP

## Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. Fork o repositório
2. Crie uma branch para sua feature (`git checkout -b feature/MinhaFeature`)
3. Commit suas mudanças (`git commit -m 'Add: Nova funcionalidade'`)
4. Push para a branch (`git push origin feature/MinhaFeature`)
5. Abra um Pull Request

## Licença

**TODO**: Adicionar arquivo LICENSE e especificar o tipo de licença (MIT, GPL, Apache 2.0, etc.).

Informações de licença não especificadas. Por favor, contate o autor para detalhes de licenciamento.

## Créditos

- **Repositório**: https://github.com/berzersks/libspech
- **Autor**: berzersks
- **Tecnologias**: PHP 8.4+, Swoole, SIP/RTP/RTCP

### Extensões PHP Relacionadas

- **bcg729**: https://github.com/berzersks/bcg729 - Codec G.729 para PHP
- **opus**: https://github.com/berzersks/opus - Codec Opus para PHP
- **psampler**: https://github.com/berzersks/psampler - Utilitários de amostragem de áudio

---

## Tecnologias Utilizadas

| Tecnologia   | Versão         | Descrição                         |
|--------------|----------------|-----------------------------------|
| **PHP**      | 8.4.13+        | Linguagem principal               |
| **Swoole**   | 6.0+           | Framework assíncrono e corrotinas |
| **SIP**      | RFC 3261       | Session Initiation Protocol       |
| **RTP/RTCP** | RFC 3550       | Real-time Transport Protocol      |
| **SDP**      | RFC 4566       | Session Description Protocol      |
| **bcg729**   | 1.0 (Opcional) | Codec G.729                       |
| **opus**     | 1.0 (Opcional) | Codec Opus com recursos avançados |
| **psampler** | 1.0 (Opcional) | Resampling de áudio               |

---

## API das Extensões de Áudio

### bcg729Channel (G.729 Codec)

```php
// Criar encoder/decoder G.729
$g729 = new bcg729Channel();

// Codificar PCM para G.729
$encoded = $g729->encode($pcmData);

// Decodificar G.729 para PCM
$decoded = $g729->decode($encoded);

// Obter informações do codec
$g729->info();

// Fechar o codec
$g729->close();
```

**Funções auxiliares**:

```php
// Converter PCMA para PCM
$pcm = decodePcmaToPcm($pcmaData);

// Converter PCMU para PCM
$pcm = decodePcmuToPcm($pcmuData);

// Converter endianness PCM
$pcmBE = pcmLeToBe($pcmLE);
```

### opusChannel (Opus Codec)

```php
// Criar encoder/decoder Opus (48kHz, mono)
$opus = new opusChannel(48000, 1);

// Configurar parâmetros de qualidade
$opus->setBitrate(64000);           // Bitrate em bps (8000-510000)
$opus->setComplexity(10);           // Complexidade 0-10 (maior = melhor qualidade)
$opus->setVBR(true);                // Variable Bitrate (economiza banda)
$opus->setDTX(true);                // Discontinuous Transmission (silêncio)
$opus->setSignalVoice(true);        // Otimizar para voz

// Codificar PCM para Opus (com resampling automático)
$encoded = $opus->encode($pcm8kHz, 8000);  // PCM 8kHz → Opus 48kHz

// Decodificar Opus para PCM (com resampling automático)
$decoded = $opus->decode($encoded, 8000);  // Opus 48kHz → PCM 8kHz

// Recursos Avançados de Processamento de Áudio

// Resampling manual
$pcm48k = $opus->resample($pcm8k, 8000, 48000);

// Melhorar clareza de voz (noise reduction + enhancement)
$enhanced = $opus->enhanceVoiceClarity($pcmData, 0.8);  // intensity: 0.0-1.0

// Melhoramento espacial estéreo (apenas para stereo=2 canais)
$spatial = $opus->spatialStereoEnhance($stereoPcm, 1.5, 0.8);  // width, depth

// Resetar encoder/decoder
$opus->reset();

// Destruir instância
$opus->destroy();
```

**Parâmetros do construtor**:

- `$sample_rate`: Taxa de amostragem (8000, 12000, 16000, 24000, 48000 Hz)
- `$channels`: Número de canais (1=mono, 2=stereo)

**Novos métodos adicionados**:

- `resample()` - Reamostrar PCM entre diferentes taxas
- `enhanceVoiceClarity()` - Redução de ruído e melhoria de voz
- `spatialStereoEnhance()` - Efeito espacial para áudio estéreo

### psampler (Audio Resampling)

```php
// Resample de 8kHz para 48kHz
$pcm48k = resampler($pcm8k, 8000, 48000);

// Resample de 48kHz para 8kHz
$pcm8k = resampler($pcm48k, 48000, 8000);

// Resample com conversão para big-endian
$pcmBE = resampler($pcmLE, 8000, 16000, true);
```

**Parâmetros**:

- `$input`: Dados PCM de entrada (string)
- `$src_rate`: Taxa de amostragem de origem (Hz)
- `$dst_rate`: Taxa de amostragem de destino (Hz)
- `$to_be`: Converter para big-endian (opcional, padrão: false)

---

## Exemplos de Uso das Extensões

### Exemplo 1: Usar Opus para Chamadas VoIP

```php
<?php

include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    $phone = new trunkController('user', 'pass', 'sip.example.com', 5060);
    $phone->register(2);

    // Criar codec Opus
    $opus = new opusChannel(48000, 1);
    $opus->setBitrate(24000);        // 24 kbps (ótimo para voz)
    $opus->setComplexity(8);
    $opus->setVBR(true);
    $opus->setDTX(true);
    $opus->setSignalVoice(true);

    $phone->mountLineCodecSDP('opus/48000');

    $phone->onReceiveAudio(function ($pcm, $peer, $phone) use ($opus) {
        // Melhorar clareza da voz recebida
        $enhanced = $opus->enhanceVoiceClarity($pcm, 0.7);
        $phone->bufferAudio .= $enhanced;
    });

    $phone->onAnswer(function ($phone) use ($opus) {
        $phone->receiveMedia();

        // Enviar áudio pré-gravado
        $audioFile = file_get_contents('greeting.pcm');

        // Resample se necessário (8kHz → 48kHz)
        $resampled = $opus->resample($audioFile, 8000, 48000);

        // Enviar via RTP (implementação personalizada)
        // $phone->sendAudio($resampled);
    });

    $phone->call('5511999999999');
});
```

### Exemplo 2: Transcodificar Áudio

```php
<?php

// Carregar áudio PCMU (G.711 μ-law)
$pcmuData = file_get_contents('audio.pcmu');

// Converter PCMU → PCM
$pcm = decodePcmuToPcm($pcmuData);

// Resample PCM de 8kHz → 48kHz
$pcm48k = resampler($pcm, 8000, 48000);

// Codificar para Opus
$opus = new opusChannel(48000, 1);
$opus->setBitrate(32000);
$opusData = $opus->encode($pcm48k);

// Salvar Opus
file_put_contents('audio.opus', $opusData);

// Decodificar de volta
$decodedPcm = $opus->decode($opusData);

// Resample de volta para 8kHz
$pcm8k = resampler($decodedPcm, 48000, 8000);

// Salvar como WAV
// (usar saveBufferToWavFile do trunkController)
```

### Exemplo 3: Melhorar Qualidade de Voz

```php
<?php

// Carregar áudio com ruído
$noisyPcm = file_get_contents('noisy_recording.pcm');

// Criar codec Opus para processamento
$opus = new opusChannel(48000, 1);

// Aplicar melhoramento de clareza de voz
$cleanPcm = $opus->enhanceVoiceClarity($noisyPcm, 0.9);  // Alta intensidade

// Salvar áudio limpo
file_put_contents('clean_recording.pcm', $cleanPcm);
```

### Exemplo 4: Codec G.729

```php
<?php

// Criar encoder/decoder G.729
$g729 = new bcg729Channel();

// Carregar PCM (8kHz, 16-bit)
$pcm = file_get_contents('audio.pcm');

// Codificar para G.729 (alta compressão)
$g729Data = $g729->encode($pcm);

echo "Tamanho original: " . strlen($pcm) . " bytes\n";
echo "Tamanho G.729: " . strlen($g729Data) . " bytes\n";
echo "Compressão: " . round((1 - strlen($g729Data)/strlen($pcm)) * 100) . "%\n";

// Decodificar
$decodedPcm = $g729->decode($g729Data);

// Fechar
$g729->close();
```

---

## Notas Importantes

### Sobre Comunicação de Voz Real

Esta biblioteca implementa **comunicação de voz bidirecional completa**:

- **Não é apenas sinalização** - Implementa tanto SIP (sinalização) quanto RTP (mídia)
- **Áudio real em tempo real** - Transmite e recebe pacotes de áudio via UDP
- **Pronto para produção** - Testado com servidores SIP reais (Asterisk, FreeSWITCH, etc.)
- **Baixa latência** - Processamento assíncrono com Swoole para performance máxima

### Diferencial

Ao contrário de muitas bibliotecas SIP em PHP que apenas gerenciam sinalização, **libspech** é uma solução completa que:

- Registra e autentica com servidores SIP
- Negocia parâmetros de mídia via SDP
- **Transmite e recebe áudio real via RTP**
- Suporta múltiplos codecs com conversão automática
- Processa eventos em tempo real

---

## Status do Projeto

**Status**: Funcional e em desenvolvimento ativo

### Características Atuais

- Registro SIP com autenticação
- Chamadas VoIP bidirecionais
- Transmissão e recepção RTP
- Suporte a múltiplos codecs
- Gravação de áudio
- DTMF (RFC 2833)

### Roadmap

- [ ] Suporte a chamadas de entrada (servidor SIP)
- [ ] Implementar SRTP/TLS para segurança
- [ ] Suporte a IPv6
- [ ] Framework de testes automatizados
- [ ] Completar suporte ao Opus codec
- [ ] Suporte a G.722 (wideband)
- [ ] Documentação da API completa
- [ ] Exemplos adicionais (IVR, conferência, transcrição)

---

## Suporte

Para dúvidas, sugestões ou reportar problemas:

- **Issues**: [GitHub Issues](https://github.com/berzersks/libspech/issues)
- **Discussões**: [GitHub Discussions](https://github.com/berzersks/libspech/discussions)

---

**Desenvolvido para a comunidade PHP VoIP**
