# libspech

[![PHP Version](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://www.php.net/)
[![Swoole](https://img.shields.io/badge/Swoole-6.0+-green.svg)](https://www.swoole.com/)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](LICENSE.txt)
[![Website](https://img.shields.io/badge/Website-spechshop.com-orange.svg)](https://spechshop.com)

Biblioteca VoIP SIP/RTP em tempo real para PHP, construída com corrotinas Swoole. Faça e receba chamadas telefônicas de PHP, transmita
audio RTP, manipule DTMF e grave áudio.

> **📖 OPEN SOURCE** - Copyright © 2026 Lotus / berzersks
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

> 📘 **Nova Documentação**: Veja **[SIGNALING_ARRAYS.md](SIGNALING_ARRAYS.md)** para entender em profundidade como os arrays de sinalização SIP são construídos e processados.

Este README reflete o repositório a partir de 2025-11-24.

## Índice

- [Stack](#stack)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Guia de Aprendizado Progressivo](#guia-de-aprendizado-progressivo)
  - [Sessão 1: Configurações Iniciais](#sessão-1-configurações-iniciais)
  - [Sessão 2: Inicialização do Ambiente de Corotina](#sessão-2-inicialização-do-ambiente-de-corotina)
  - [Sessão 3: Configuração de Credenciais SIP](#sessão-3-configuração-de-credenciais-sip)
  - [Sessão 4: Registro SIP](#sessão-4-registro-sip)
  - [Sessão 5: Configuração de Callbacks de Eventos](#sessão-5-configuração-de-callbacks-de-eventos)
  - [Sessão 6: Configuração de Codec e Recursos de Áudio](#sessão-6-configuração-de-codec-e-recursos-de-áudio)
  - [Sessão 7: Fluxo de Interação na Chamada](#sessão-7-fluxo-de-interação-na-chamada)
  - [Sessão 8: Inicialização da Chamada](#sessão-8-inicialização-da-chamada)
  - [Sessão 9: Finalização e Limpeza](#sessão-9-finalização-e-limpeza)
- [API de Callbacks](#api-de-callbacks)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Documentação dos Módulos](#documentação-dos-módulos)
- [Codecs](#codecs)
- [Arquitetura e Fluxo de Dados](#arquitetura-e-fluxo-de-dados)
- [Exemplos Avançados](#exemplos-avançados)
- [Licença](#licença)

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

## Guia de Aprendizado Progressivo

Este guia segue a estrutura do arquivo `example.php`, explicando cada sessão de forma progressiva. Recomendamos estudar sessão por sessão para entender o fluxo completo de uma chamada VoIP.

> 💡 **Dica**: Execute `php example.php` enquanto lê este guia para ver cada conceito em ação.

### Sessão 1: Configurações Iniciais

Antes de começar, precisamos preparar o ambiente PHP:

```php
<?php
// Aumenta o limite de memória para 1GB - necessário para processar áudio
ini_set('memory_limit', '1024M');

// Importa as classes necessárias do sistema
use libspech\Cli\cli;
use libspech\Sip\trunkController;

// Habilita o suporte a corotinas do Swoole para execução assíncrona
\Swoole\Runtime::enableCoroutine();

// Carrega o autoloader para importar todas as dependências do projeto
include 'plugins/autoloader.php';
```

**Por que aumentar a memória?**
- Buffers de áudio podem acumular rapidamente (especialmente em chamadas longas)
- Codecs como Opus trabalham com chunks grandes de dados
- Previne erros de memória durante gravações

**Configurando credenciais:**

1. Copie o arquivo de exemplo:
   ```bash
   cp .env.example .env
   ```

2. Edite `.env` com suas credenciais SIP:
   ```bash
   SIP_USERNAME=seu_username
   SIP_PASSWORD=sua_password
   SIP_HOST=sip.example.com
   ```

### Sessão 2: Inicialização do Ambiente de Corotina

O Swoole permite executar código assíncrono sem callbacks complexos:

```php
// Cria o ambiente de execução em corotina do Swoole
\Swoole\Coroutine\run(function () {
    // Cria uma nova corotina para executar o código SIP de forma assíncrona
    \Swoole\Coroutine::create(function () {

        // Todo o código da chamada vai aqui
        // Executa de forma não-bloqueante

    });
});
```

**Vantagens das corotinas:**
- ✅ Código sequencial (sem callback hell)
- ✅ Milhares de chamadas simultâneas em um thread
- ✅ I/O não-bloqueante automático
- ✅ Zero overhead de sincronização

### Sessão 3: Configuração de Credenciais SIP

Carregue e valide as credenciais do servidor SIP:

```php
// Busca as credenciais SIP das variáveis de ambiente
$username = getenv('SIP_USERNAME') ?: '';
$password = getenv('SIP_PASSWORD') ?: '';
$domain = getenv('SIP_HOST') ?: 'spechshop.com';

// Valida se o domínio é um IP ou hostname
// Se for hostname, resolve para IP usando DNS
if (!filter_var($domain, FILTER_VALIDATE_IP)) {
    $host = gethostbyname($domain);
} else {
    $host = $domain;
}

// Instancia o controlador do trunk SIP com as credenciais
$phone = new trunkController($username, $password, $host);
```

**Resolução de DNS:**
- SIP trabalha com endereços IP
- Se você forneceu um hostname, ele é resolvido automaticamente
- O IP é necessário para comunicação UDP direta

### Sessão 4: Registro SIP

Antes de fazer ou receber chamadas, é necessário registrar no servidor:

```php
// Tenta registrar no servidor SIP com timeout de 10 segundos
// Se falhar, lança uma exceção e interrompe a execução
if (!$phone->register(10)) {
    throw new \Exception("Erro ao registrar");
}
```

**O que acontece no registro:**
1. Envia mensagem REGISTER para o servidor
2. Servidor responde com desafio de autenticação (401 Unauthorized)
3. Cliente recalcula credenciais usando Digest Authentication
4. Envia novo REGISTER com credenciais
5. Servidor confirma com 200 OK

**Parâmetro de retry:**
- `register(10)` = tenta 10 vezes antes de falhar
- Cada tentativa aguarda resposta do servidor
- Útil para redes instáveis

### Sessão 5: Configuração de Callbacks de Eventos

A API é baseada em eventos. Configure callbacks para reagir ao ciclo de vida da chamada:

```php
// Callback executado quando uma chamada está tocando (ringing)
$phone->onRinging(function ($phone) {
    cli::pcl("Chamada recebida", "yellow");
});

// Callback executado quando a chamada é desligada (hangup/bye)
$phone->onHangup(function (trunkController $phone) {
    // Salva o buffer de áudio gravado em um arquivo WAV
    $phone->saveBufferToWavFile('rec.wav', $phone->getBuffer());
    // Desbloqueia a corotina para continuar a execução
    $phone->unblockCoroutine();
    cli::pcl("Bye recebido", "red");
});

// Callback executado quando a chamada é recebida/respondida
$phone->onAnswer(function (trunkController $phone) {
    // Inicia o recebimento de mídia (áudio RTP)
    $phone->receiveMedia();
    cli::pcl("Chamada aceita", "green");
});

// Callback executado quando uma tecla DTMF é pressionada remotamente
$phone->onKeyPress(function ($event, $peer) use ($phone) {
    cli::pcl("Digitando: " . $event, "yellow");
});
```

**Ciclo de vida de uma chamada outbound:**
```
call() → onRinging() → onAnswer() → [conversa] → onHangup()
```

### Sessão 6: Configuração de Codec e Recursos de Áudio

Configure qual codec usar e recursos adicionais de áudio:

```php
// Define o codec de áudio como OPUS 48kHz mono (1 canal)
$phone->mountLineCodecSDP('OPUS/48000/1');

// Habilita a gravação de áudio durante a chamada
$phone->enableAudioRecording();

// Habilita VAD (Voice Activity Detection) - detecta quando há voz ativa
$phone->enableVAD();

// Callback executado quando o VAD detecta mudança entre voz e silêncio
$phone->onVadChange(function ($isVoiceActive, $energy, $id) {
    cli::pcl(
        "VAD: $id " . ($isVoiceActive ? 'voice' : 'silence') . " Energy: $energy " . date('H:i:s'),
        ($isVoiceActive ? 'bold_green' : 'bold_red')
    );
});
```

**Codecs disponíveis:**
- `OPUS/48000/1` - Alta qualidade, 48kHz mono
- `OPUS/48000/2` - Alta qualidade, 48kHz estéreo
- `G729/8000` - Baixa largura de banda, 8kHz
- `PCMU/8000` - G.711 µ-law (sem compressão)
- `PCMA/8000` - G.711 A-law (sem compressão)

**VAD (Voice Activity Detection):**
- Detecta quando há fala ativa na chamada
- Útil para economizar processamento
- Pode ser usado para transcrição sob demanda

### Sessão 7: Fluxo de Interação na Chamada

Depois que a chamada é atendida, você pode interagir com ela:

```php
$phone->onAnswer(function (trunkController $phone) {
    $phone->receiveMedia();

    // Aguarda 10 segundos de forma interruptível (pode ser cancelado se receber BYE)
    \libspech\Sip\interruptibleSleep(10, $phone->receiveBye);

    // Envia DTMF (tom de teclado) - caractere '*' com duração de 160ms
    $phone->send2833('*', 160);

    // Aguarda mais 10 segundos de forma interruptível
    \libspech\Sip\interruptibleSleep(10, $phone->receiveBye);

    // Envia DTMF com o valor 999999999 e duração de 960ms
    $phone->send2833(999999999, 960);

    // Aguarda mais 10 segundos antes de encerrar
    \libspech\Sip\interruptibleSleep(10, $phone->receiveBye);

    // Envia BYE para encerrar a chamada
    $phone->bye();

    // Define flags indicando que a chamada foi encerrada
    $phone->receiveBye = true;
    $phone->callActive = false;
});
```

**interruptibleSleep:**
- Sleep que pode ser interrompido se receber BYE
- Evita aguardar desnecessariamente se a chamada for desligada
- Sempre use em vez de `sleep()` ou `Coroutine::sleep()`

**Envio de DTMF:**
- `send2833()` envia tons DTMF (RFC 2833)
- Primeiro parâmetro: dígito(s) a enviar
- Segundo parâmetro: duração em milissegundos
- Útil para navegar em URAs (IVR)

### Sessão 8: Inicialização da Chamada

Depois de tudo configurado, inicie a chamada:

```php
// Realiza uma chamada de saída para o número especificado
$phone->call('5511999887766');
```

**O que acontece internamente:**
1. Gera Call-ID único
2. Monta mensagem INVITE com SDP (codecs oferecidos)
3. Envia para o servidor SIP
4. Aguarda 180 Ringing → dispara onRinging()
5. Aguarda 200 OK → dispara onAnswer()
6. Envia ACK para confirmar
7. Chamada estabelecida → mídia RTP flui

### Sessão 9: Finalização e Limpeza

Sempre libere recursos ao final:

```php
cli::pcl("Script finalizado", "green");

// Fecha a conexão SIP e libera recursos
$phone->close();

cli::pcl("Processo cancelado", "red");
```

**Por que fechar explicitamente?**
- Libera sockets UDP
- Encerra threads de recepção de mídia
- Evita vazamento de memória
- Envia UNREGISTER ao servidor

### Exemplo Completo Comentado

O arquivo `example.php` contém todas as 9 sessões integradas. Execute-o para ver o fluxo completo:

```bash
php example.php
```

**Você verá:**
- ✅ Registro SIP confirmado
- ✅ Chamada sendo realizada
- ✅ Status de ringing
- ✅ Chamada atendida
- ✅ VAD detectando voz/silêncio
- ✅ DTMF sendo enviado
- ✅ Áudio sendo gravado em `rec.wav`
- ✅ Chamada finalizada

## Início Rápido - Exemplo Mínimo

Para começar rapidamente, aqui está um exemplo mínimo funcional:

```php
<?php
use libspech\Sip\trunkController;

\Swoole\Runtime::enableCoroutine();
include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    $phone = new trunkController(
        getenv('SIP_USERNAME'),
        getenv('SIP_PASSWORD'),
        getenv('SIP_HOST')
    );

    if (!$phone->register(2)) {
        throw new \Exception('Falha no registro');
    }

    $phone->mountLineCodecSDP('OPUS/48000/1');

    $phone->onAnswer(function (trunkController $phone) {
        $phone->receiveMedia();
        echo "Chamada atendida!\n";
    });

    $phone->onHangup(function (trunkController $phone) {
        echo "Chamada finalizada!\n";
        $phone->close();
    });

    $phone->call('5511999887766');
});
```

**Configure o `.env` antes de executar:**
```bash
cp .env.example .env
# Edite .env com suas credenciais SIP
php example.php
```

## Variáveis de Ambiente

A biblioteca utiliza variáveis de ambiente para gerenciar credenciais SIP de forma segura:

```bash
# Credenciais SIP
SIP_USERNAME=seu_username      # Nome de usuário fornecido pelo provedor SIP
SIP_PASSWORD=sua_password      # Senha fornecida pelo provedor SIP
SIP_HOST=sip.example.com       # Domínio ou IP do servidor SIP
SIP_PORT=5060                  # Porta SIP (padrão: 5060)
SIP_TIMEOUT=120                # Timeout global em segundos
```

**Como configurar:**

1. Copie o arquivo de exemplo:
   ```bash
   cp .env.example .env
   ```

2. Edite `.env` com suas credenciais

3. As variáveis são carregadas automaticamente pelo `autoloader.php`

**Carregamento no código:**
```php
$username = getenv('SIP_USERNAME') ?: '';
$password = getenv('SIP_PASSWORD') ?: '';
$host = getenv('SIP_HOST') ?: 'spechshop.com';
```

> ⚠️ **Segurança**: Nunca comite o arquivo `.env` no controle de versão. Ele está incluído no `.gitignore` por padrão.

## Referência da API

### Classe `trunkController`

A classe principal para gerenciar chamadas SIP. Localizada em `plugins/Utils/libspech/trunkController.php`.

#### Construtor

```php
new trunkController(
    string $username,  // Nome de usuário SIP
    string $password,  // Senha SIP
    string $host,      // IP do servidor SIP
    int $port = 5060   // Porta SIP (padrão: 5060)
)
```

#### Métodos de Controle de Chamada

```php
// Registrar no servidor SIP
bool register(int $retries = 3)
// Retorna: true se registrado com sucesso, false caso contrário
// $retries: número de tentativas antes de falhar

// Realizar chamada de saída
void call(string $number)
// $number: número de telefone para chamar (formato livre)

// Encerrar chamada
void bye()
// Envia mensagem BYE e encerra a chamada ativa

// Fechar conexão e liberar recursos
void close()
// SEMPRE chame este método ao final para liberar sockets
```

#### Configuração de Codec e Mídia

```php
// Configurar codec SDP
void mountLineCodecSDP(string $codec)
// Exemplos:
//   'OPUS/48000/1' - Opus 48kHz mono
//   'OPUS/48000/2' - Opus 48kHz estéreo
//   'G729/8000'    - G.729 8kHz
//   'PCMU/8000'    - G.711 µ-law
//   'PCMA/8000'    - G.711 A-law

// Iniciar recepção de mídia RTP
void receiveMedia()
// DEVE ser chamado dentro de onAnswer()

// Definir arquivo de áudio para envio
void defineAudioFile(string $path)
// $path: caminho para arquivo WAV

// Salvar buffer de áudio em arquivo WAV
void saveBufferToWavFile(string $path, string $data)
// $path: caminho de saída
// $data: buffer PCM bruto

// Obter buffer de áudio gravado
string getBuffer()
// Retorna: buffer PCM completo da chamada
// IMPORTANTE: Requer que enableAudioRecording() tenha sido chamado
```

#### Recursos de Áudio Avançados

```php
// Habilitar gravação de áudio
void enableAudioRecording()
// Grava automaticamente todo áudio recebido
// Use getBuffer() no onHangup para obter o áudio completo

// Habilitar VAD (Voice Activity Detection)
void enableVAD()
// Detecta quando há voz ativa vs silêncio

// Enviar DTMF (RFC 2833)
void send2833(int|string $digits, int $durationMs)
// $digits: dígito(s) a enviar (0-9, *, #, A-D)
// $durationMs: duração em milissegondos
```

#### Callbacks de Eventos

```php
// Chamada recebida/tocando (180 Ringing)
void onRinging(callable $callback)
// Callback: function($phone) { }

// Chamada atendida (200 OK + ACK)
void onAnswer(callable $callback)
// Callback: function(trunkController $phone) { }
// IMPORTANTE: Chame receiveMedia() aqui

// Chamada finalizada (BYE recebido)
void onHangup(callable $callback)
// Callback: function(trunkController $phone) { }
// Recomendado: Salvar áudio e chamar close()

// Tecla DTMF pressionada remotamente
void onKeyPress(callable $callback)
// Callback: function(string $digit, array $peer) { }
// $digit: caractere pressionado
// $peer: ['ip' => '...', 'port' => ...]

// Áudio PCM recebido (para processamento em tempo real)
void onReceivePcm(callable $callback)
// Callback: function(string $pcmData, array $peer, trunkController $phone) { }
// $pcmData: buffer PCM bruto
// Chamado continuamente durante a chamada
// NOTA: Para apenas gravar, use enableAudioRecording() + getBuffer()

// VAD mudou de estado
void onVadChange(callable $callback)
// Callback: function(bool $isVoiceActive, float $energy, string $callId) { }
// $isVoiceActive: true = voz detectada, false = silêncio
// $energy: nível de energia do sinal
```

#### Configuração Avançada

```php
// Definir timeout global da chamada
void defineTimeout(int $seconds)
// Encerra automaticamente após $seconds

// Desbloquear corotina (uso interno)
void unblockCoroutine()
// Libera corotinas aguardando em blockCoroutine()
```

### Funções Auxiliares

```php
// Sleep interruptível (namespace libspech\Sip)
void interruptibleSleep(int $seconds, bool &$flag)
// Dorme $seconds ou até $flag = true
// Use sempre em vez de sleep() dentro de callbacks

// Obter IP local (classe network)
?string libspech\Network\network::getLocalIp()
// Retorna IP não-loopback da máquina

// Verificar se IP é privado
bool libspech\Network\network::isPrivateIp(string $ip)

// Alocar porta RTP disponível
int libspech\Network\network::allocateRtpPort()
// Retorna porta livre entre 10000-62000
```

### Classe `cli` - Utilitários de Terminal

```php
use libspech\Cli\cli;

// Imprimir texto colorido
cli::pcl(string $message, string $color = 'white')

// Cores disponíveis:
// 'black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white'
// 'bold_black', 'bold_red', 'bold_green', 'bold_yellow', ...

// Exemplo:
cli::pcl("Chamada atendida!", "green");
cli::pcl("Erro ao registrar", "bold_red");
```

## Estrutura do Projeto

```
libspech/
├── example.php
├── plugins/                           # Núcleo da biblioteca com sistema de autoload automático
│   ├── autoloader.php                 # Autoloader inteligente + carregamento de .env
│   ├── configInterface.json           # Configuração de diretórios para autoload
│   ├── Packet/                        # Renderização de mensagens SIP/SDP
│   │   └── controller/
│   │       └── renderMessages.php     # Gerador de mensagens de resposta SIP
│   └── Utils/
│       ├── cache/                     # Sistema de cache e RPC
│       │   ├── cache.php              # Cache em memória global
│       │   └── rpcClient.php          # Cliente RPC para comunicação
│       ├── cli/                       # Utilitários de linha de comando
│       │   └── cli.php                # Helper de cores e logging
│       ├── libspech/                  # Controladores principais
│       │   ├── trunkController.php    # Classe principal para gerenciar chamadas SIP
│       │   └── functionsTrunkController.php  # Funções auxiliares
│       ├── network/                   # Utilitários de rede
│       │   └── network.php            # Detecção de IP local, validação, alocação de portas
│       └── sip/                       # Stack SIP/RTP completo
│           ├── sip.php                # Parser de mensagens SIP (INVITE, REGISTER, BYE)
│           ├── rtpChannel.php         # Gerenciamento de canais RTP (headers, codecs)
│           ├── mediaChannel.php       # Recepção/envio de áudio com suporte a múltiplos codecs
│           ├── rtpc.php               # Controle RTP/RTCP (stat reporting)
│           ├── DtmfEvent.php          # Eventos DTMF (RFC 2833)
│           └── AdaptiveBuffer.php     # Buffer adaptativo para mitigação de jitter
├── stubs/                             # Stubs IDE para extensões nativas
│   ├── bcg729Channel.php              # Extensão bcg729 (G.729 codec)
│   ├── opusChannel.php                # Extensão opus (Opus codec)
│   └── psampler.php                   # Extensão psampler (reamostragem de áudio)
├── LICENSE.txt
├── README.md
└── SECURITY.md
```

### Sistema de Autoload

O arquivo `autoloader.php` realiza três operações ao ser incluído:

1. **Carregamento de .env**: Procura por arquivo `.env` e o cria a partir de `.env.example` se não existir
2. **Parsing de variáveis de ambiente**: Lê o arquivo `.env` e popula com `putenv()`
3. **Autoload automático**: Lê `configInterface.json` e inclui todos os arquivos PHP dos diretórios listados

```php
// configInterface.json define quais diretórios autocarregar
{
  "autoload": [
    "Utils/cache",      // Sistema de cache
    "Utils/cli",        // Utilitários CLI
    "Utils/sip",        // Stack SIP/RTP
    "Utils/libspech",   // Controladores principais
    "Utils/network",    // Utilitários de rede
    "Packet/controller" // Renderizadores de mensagens
  ],
  "reloadCaseFileModify": []  // TODO: hot reload em desenvolvimento
}
```

**Namespace:** A classe principal usa `libspech\Sip\trunkController`:

```php
use libspech\Sip\trunkController;  // Correto - namespace na classe
include 'plugins/autoloader.php';
```

## Documentação dos Módulos

### Core Modules

#### `plugins/Utils/libspech/trunkController.php` (Controlador Principal)

Classe `libspech\Sip\trunkController` - Orquestrador central para gerenciar chamadas SIP.

**Responsabilidades principais:**
- Registro no servidor SIP (com suporte a autenticação Digest)
- Gerenciamento completo do ciclo de vida da chamada (INVITE → ACK → BYE)
- Configuração de callbacks para eventos (ringing, answer, hangup, DTMF)
- Gerenciamento de canais RTP/RTCP
- Recepção e envio de áudio

**Propriedades importantes:**
```php
public \Swoole\Coroutine\Socket $socket;        // Socket UDP para SIP
public bool $isRegistered;                       // Estado de registro
public bool $callActive;                         // Chamada em andamento?
public string $codecName;                        // Codec negociado
public array $rtpChans;                          // Canais RTP por SSRC
public int $audioReceivePort;                    // Porta para RTP
```

**Métodos principais:**
```php
register(int $retries): bool                     // Registrar no servidor SIP
call(string $number): void                       // Iniciar chamada outbound
receiveMedia(): void                             // Iniciar recepção de mídia RTP
send2833(int $code, int $duration): void        // Enviar DTMF (RFC 2833)
defineAudioFile(string $path): void             // Definir arquivo para envio
saveBufferToWavFile(string $path, string $data) // Salvar áudio capturado em WAV
mountLineCodecSDP(string $codec): void          // Configurar codec SDP
defineTimeout(int $seconds): void               // Timeout para chamada
close(): void                                    // Finalizar conexão
```

#### `plugins/Utils/sip/sip.php` (Parser SIP)

Classe `libspech\Sip\sip` - Parser de mensagens SIP/SDP.

**Responsabilidades:**
- Parse de mensagens SIP (INVITE, 200 OK, BYE, REGISTER, etc.)
- Extração de headers SIP
- Parse de ofertas/respostas SDP
- Validação de autenticação Digest

**Métodos principais:**
```php
sip::parse(string $rawMessage): array           // Parser mensagem SIP bruta
sip::extractUri(string $uri): array             // Extrair componentes de URI SIP
sip::normalizeArrayKey(string $key, ...): array // Normalizar chaves de headers
```

**Exemplo de uso:**
```php
$message = "INVITE sip:user@domain.com SIP/2.0\r\n...";
$parsed = sip::parse($message);

// $parsed contém:
// - 'method' => 'INVITE'
// - 'headers' => [...] 
// - 'sdp' => [...] (se houver SDP no body)
```

#### `plugins/Utils/sip/mediaChannel.php` (Canal de Mídia)

Classe `libspech\Rtp\MediaChannel` - Gerenciamento de recepção/envio de áudio RTP.

**Responsabilidades:**
- Recepção de pacotes RTP de múltiplos peers
- Decodificação de áudio (G.729, Opus, PCMU, PCMA)
- Detecção de voz (VAD - Voice Activity Detection)
- Detecção de DTMF (RFC 2833)
- Buffer adaptativo para mitigação de jitter

**Métodos principais:**
```php
onReceive(callable $callback): void              // Callback ao receber áudio
onDtmf(callable $callback): void                 // Callback DTMF recebido
onVadChange(callable $callback): void            // Callback detecção de voz
enableAdaptation(bool $useBuffer): void          // Ativar buffer adaptativo
disableAdaptation(): void                        // Desativar buffer adaptativo
recordAudio(string $path): void                  // Gravar áudio em arquivo
```

**Propriedades principais:**
```php
public array $members;                           // Peers RTP conectados
public bool $vadEnabled;                         // VAD ativado?
public bool $isVoiceActive;                      // Voz detectada?
public bool $recordingEnabled;                   // Gravação ativa?
public bcg729Channel $channelEncode;             // Encoder G.729
public bcg729Channel $channelDecode;             // Decoder G.729
public ?opusChannel $opusChannel;                // Encoder/Decoder Opus
```

#### `plugins/Utils/sip/rtpChannel.php` (Canal RTP)

Classe `libspech\Rtp\rtpChannel` - Montagem/desmontagem de pacotes RTP.

**Responsabilidades:**
- Construção de headers RTP (RFC 3550)
- Gerenciamento de sequence number e timestamp
- Suporte a múltiplos payload types (codecs)
- Eventos DTMF (RFC 2833)

**Constantes de payload:**
```php
const PAYLOAD_PCMU = 0;      // G.711 µ-law
const PAYLOAD_PCMA = 8;      // G.711 A-law
const PAYLOAD_G729 = 18;     // G.729
const PAYLOAD_DTMF = 101;    // DTMF (RFC 2833)
```

**Métodos principais:**
```php
__construct(int $payloadType, int $sampleRate, int $packetTimeMs, ?int $ssrc)
encodeFrame(string $audioData): string          // Encapsular áudio em RTP
decodeFrame(string $rtpPacket): string          // Extrair áudio de RTP
sendDtmfEvent(DtmfEvent $event): string         // Montar evento DTMF
```

#### `plugins/Utils/sip/DtmfEvent.php` (Eventos DTMF)

Classe `libspech\Rtp\DtmfEvent` - Representação de eventos DTMF (RFC 2833).

**Constantes DTMF:**
```php
const DTMF_0 = 0;   // Dígito 0
const DTMF_1 = 1;   // Dígito 1
// ... 2-9 ...
const DTMF_STAR = 10;  // Símbolo *
const DTMF_HASH = 11;  // Símbolo #
const DTMF_A = 12;     // Letra A
const DTMF_B = 13;     // Letra B
const DTMF_C = 14;     // Letra C
const DTMF_D = 15;     // Letra D
```

**Uso:**
```php
$dtmf = new DtmfEvent(DtmfEvent::DTMF_5, 10, 160);
$phone->send2833($dtmf);
```

#### `plugins/Utils/sip/AdaptiveBuffer.php` (Buffer Adaptativo)

Classe `libspech\Rtp\AdaptiveBuffer` - Mitigação de jitter e perda de pacotes.

**Responsabilidades:**
- Buffering automático de pacotes RTP
- Adaptação dinâmica do tamanho do buffer baseado em jitter
- Detecção de underruns/overruns
- Métricas em tempo real

**Métodos principais:**
```php
enable(): void                                   // Ativar buffer
disable(): void                                  // Desativar buffer
push(mixed $data): bool                          // Adicionar pacote
pop(): ?mixed                                    // Remover pacote
getMetrics(): array                              // Obter estatísticas
```

#### `plugins/Utils/sip/rtpc.php` (Controle RTP/RTCP)

Classe `libspech\Rtp\rtpc` - Gerenciamento de RTCP (relatório de estatísticas).

**Responsabilidades:**
- Envio de relatórios RTCP SR (Sender Report)
- Recebimento de relatórios RTCP RR (Receiver Report)
- Tracking de estatísticas de mídia (loss, jitter, rtt)

### Utility Modules

#### `plugins/Utils/network/network.php` (Utilitários de Rede)

Classe `libspech\Network\network` - Detecção de IP local, validação e alocação de portas.

**Métodos principais:**
```php
getLocalIp(): ?string                            // Obter IP local não-loopback
isPrivateIp(string $ip): bool                    // Verificar se IP é privado
isPublicIp(string $ip): bool                     // Verificar se IP é público
allocateRtpPort(): int                           // Alocar porta RTP (10000-62000)
```

#### `plugins/Utils/cache/cache.php` (Cache em Memória)

Classe `libspech\Cache\cache` - Armazenamento de estado em `$GLOBALS`.

**Métodos principais:**
```php
get(string $key): mixed                          // Obter valor
set(string $key, mixed $value): void             // Definir valor
join(string $key, mixed $value): bool            // Adicionar a array
subJoin(string $key, string $subKey, mixed $v)   // Adicionar a array aninhado
arrayShift(string $key): mixed                   // Pop do início de array
```

#### `plugins/Utils/cli/cli.php` (Utilitários CLI)

Classe `libspech\Cli\cli` - Logging colorido e menu CLI.

**Métodos principais:**
```php
static color(string $color, string $message): string    // Colorizar texto
static pcl(string $message, string $color = 'white')    // Print colorido
```

**Cores suportadas:**
```php
'black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white',
'bold_black', 'bold_red', 'bold_green', 'bold_yellow', ...
```

#### `plugins/Packet/controller/renderMessages.php` (Renderizador SIP)

Classe `libspech\Packet\renderMessages` - Gerador de mensagens SIP de resposta.

**Métodos principais:**
```php
generateBye(array $headers200): array            // Gerar mensagem BYE
respondUserNotFound(array $headers): string      // Resposta 404
baseResponse(array $headers, string $code): string  // Resposta genérica
generateInviteResponse(array $headers): array    // Resposta 200 OK para INVITE
```

## Codecs

Payloads suportados e disponíveis no codebase:

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

## Arquitetura e Fluxo de Dados

### Fluxo de Uma Chamada Outbound (Enviada)

```
trunkController::call()
    ↓
1. Gerar Call-ID único
2. Montar INVITE (SIP)
3. Negociar SDP (codecs, porta RTP)
4. Enviar INVITE para servidor SIP
    ↓
    ← Receber 100 Trying (opcional)
    ← Receber 180 Ringing (callback onRinging)
    ↓
    ← Receber 200 OK com SDP resposta
5. Extrair IP/porta RTP remoto de SDP
6. Enviar ACK
    ↓
[Chamada conectada]
    ↓
trunkController::receiveMedia()
    ↓
MediaChannel::start()
    ↓
Loop infinito:
  ├─ Receber pacote RTP em socket UDP
  ├─ rtpChannel::decodeFrame() → áudio bruto
  ├─ Callback onReceivePcm() → usuário processa áudio
  ├─ Detectar DTMF (RFC 2833) → callback onKeyPress()
  ├─ Detectar voz (VAD) → callback onVadChange()
  └─ Buffer adaptativo mitiga jitter
    ↓
[Usuário envia BYE ou timeout]
    ↓
renderMessages::generateBye()
    ↓
Enviar BYE → Servidor SIP
    ↓
Callback onHangup()
```

### Fluxo de Uma Chamada Inbound (Recebida)

```
1. Servidor SIP recebe INVITE para seu número
2. Redireciona para seu IP:porta local
3. trunkController socket recebe INVITE
    ↓
4. sip::parse() processa INVITE + SDP
5. Extrair codec, IP/porta remoto
6. Callback onRinging()
    ↓
[Usuário aguarda decisão]
    ↓
7. Gerar SDP de resposta (codec negocia)
8. Enviar 200 OK com SDP
9. Receber ACK
    ↓
[Chamada conectada]
    ↓
Callback onAnswer()
    ↓
MediaChannel inicia recepção (similar ao outbound)
    ↓
Callback onHangup() quando recebe BYE
```

### Pilha de Módulos

```
┌─────────────────────────────────────────────┐
│       Aplicação do Usuário                  │
│  (example.php com callbacks)                │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│   trunkController (Orquestrador)            │
│   - Ciclo de vida da chamada                │
│   - Gerenciamento de estado                 │
│   - Callbacks de eventos                    │
└─────────────────┬───────────────────────────┘
                  │
        ┌─────────┴──────────┐
        │                    │
┌───────▼──────┐      ┌──────▼───────┐
│  sip.php     │      │ mediaChannel │
│  (Parser)    │      │  (RTP/RTCP)  │
│              │      │              │
│ - INVITE     │      │ - rtpChannel │
│ - 200 OK     │      │ - DtmfEvent  │
│ - BYE        │      │ - AdaptiveBuffer
│ - REGISTER   │      │ - VAD        │
│ - SDP parse  │      └──────────────┘
└───────┬──────┘
        │
┌───────▼──────────────────────────────┐
│   Socket UDP (Swoole)                │
│   - Envio/recepção de pacotes SIP    │
│   - Envio/recepção de pacotes RTP    │
└──────────────────────────────────────┘
```

### Integração com Swoole Coroutines

Toda a I/O é não-bloqueante usando corrotinas Swoole:

```php
\Swoole\Coroutine\run(function () {
    \Swoole\Coroutine::create(function () {
        // Cada coroutine = contexto de chamada isolado
        
        // I/O não-bloqueante
        $phone->register(2);        // Yield até resposta
        $phone->call($number);      // Yield até resposta
        $phone->receiveMedia();     // Yield em loop RTP
        
        // Nenhum thread, nenhum callback hell - código sequencial
    });
});
```

**Benefícios:**
- ✅ Milhares de chamadas simultâneas em um thread
- ✅ Zero overhead de sincronização
- ✅ Código linear e fácil de entender
- ✅ Integração nativa com extensões Swoole (timers, queues, etc.)

## Casos de Uso Práticos

### 🤖 Automação de Atendimento (IVR)
- Receber chamadas e tocar mensagens de áudio
- Detectar DTMF para navegação em menus
- Gravar mensagens de voz dos usuários
- Transferir para números específicos baseado em escolha

### 📞 Call Center e Discador Automático
- Realizar chamadas em massa (dialer)
- Gravar todas as conversas
- Detectar quando há voz ativa (VAD) para análise
- Enviar DTMF para navegar em URAs de terceiros

### 🎙️ Transcrição de Chamadas em Tempo Real
- Capturar áudio PCM durante a chamada
- Integrar com APIs de transcrição (Google, Whisper, etc.)
- Processar apenas quando VAD detecta voz
- Salvar transcrição e áudio sincronizados

### 🔔 Notificações por Telefone
- Enviar alertas críticos via chamada telefônica
- Tocar mensagens de áudio pré-gravadas
- Confirmar recebimento via DTMF
- Retry automático se não atendida

### 📊 Monitoramento de Qualidade (QoS)
- Analisar jitter e perda de pacotes RTP
- Medir latência e qualidade de áudio
- Detectar problemas de rede
- Gerar relatórios de métricas

## Notas de Uso

### Rede e NAT
- Certifique-se de que o IP local e portas sejam alcançáveis pelo servidor SIP
- STUN/travessia NAT não está incluída nesta versão
- Para ambientes com NAT, considere configurar port forwarding para portas RTP (10000-62000)
- Use `network::getLocalIp()` para detectar automaticamente seu IP local

### Segurança
- Esta biblioteca foca em SIP básico sobre UDP
- TLS/SRTP não estão implementados atualmente
- Use em redes confiáveis ou configure VPN/túnel seguro
- Nunca exponha credenciais SIP em código (use `.env`)

### Performance
- Cada chamada roda em uma corotina separada (zero custo de thread)
- Capaz de gerenciar milhares de chamadas simultâneas
- Buffer adaptativo mitiga jitter automaticamente
- Codecs leves (G.729) economizam largura de banda

## Exemplos Avançados

Esta seção demonstra implementações completas para casos de uso reais.

### 📞 Exemplo 1: Discador Automático com Gravação Múltipla

Realize múltiplas chamadas simultâneas e grave cada uma em arquivo separado:

```php
<?php
use libspech\Sip\trunkController;
use libspech\Cli\cli;

\Swoole\Runtime::enableCoroutine();
include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    $calls = [
        '5511999999999' => 'cliente_a',
        '5511888888888' => 'cliente_b',
        '5511777777777' => 'cliente_c',
    ];

    foreach ($calls as $number => $clientName) {
        // Cada chamada roda em corotina separada
        \Swoole\Coroutine::create(function () use ($number, $clientName) {
            $phone = new trunkController(
                getenv('SIP_USERNAME'),
                getenv('SIP_PASSWORD'),
                getenv('SIP_HOST')
            );

            if (!$phone->register(2)) {
                cli::pcl("Falha ao registrar para $clientName", "red");
                return;
            }

            $phone->mountLineCodecSDP('OPUS/48000/1');
            $phone->enableAudioRecording();

            $phone->onRinging(function ($p) use ($clientName) {
                cli::pcl("[$clientName] Chamando...", "yellow");
            });

            $phone->onAnswer(function ($p) use ($clientName) {
                $p->receiveMedia();
                cli::pcl("[$clientName] Chamada atendida!", "green");
            });

            $phone->onHangup(function ($p) use ($number, $clientName) {
                // Obtém o buffer completo gravado automaticamente
                $audioBuffer = $p->getBuffer();
                $timestamp = date('Y-m-d_H-i-s');
                $filename = "recordings/{$clientName}_{$number}_{$timestamp}.wav";
                $p->saveBufferToWavFile($filename, $audioBuffer);
                cli::pcl("[$clientName] Gravação salva: $filename", "green");
                $p->close();
            });

            // Inicia a chamada
            $phone->call($number);
        });
    }
});

cli::pcl("Todas as chamadas finalizadas", "green");
```

**Saída esperada:**
```
[cliente_a] Chamando...
[cliente_b] Chamando...
[cliente_c] Chamando...
[cliente_a] Chamada atendida!
[cliente_c] Chamada atendida!
[cliente_b] Chamada atendida!
[cliente_a] Gravação salva: recordings/cliente_a_5511999999999_2025-02-16_14-30-25.wav
[cliente_c] Gravação salva: recordings/cliente_c_5511777777777_2025-02-16_14-30-28.wav
[cliente_b] Gravação salva: recordings/cliente_b_5511888888888_2025-02-16_14-30-31.wav
Todas as chamadas finalizadas
```

### 🎛️ Exemplo 2: Menu IVR Interativo com DTMF

Implemente um sistema IVR que responde a teclas pressionadas pelo usuário:

```php
<?php
use libspech\Sip\trunkController;
use libspech\Cli\cli;

\Swoole\Runtime::enableCoroutine();
include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    \Swoole\Coroutine::create(function () {
        $phone = new trunkController(
            getenv('SIP_USERNAME'),
            getenv('SIP_PASSWORD'),
            getenv('SIP_HOST')
        );

        $phone->register(2);
        $phone->mountLineCodecSDP('OPUS/48000/1');

        // Estado do menu
        $menuState = 'main';
        $selectedOption = null;

        $phone->onAnswer(function ($p) use (&$menuState) {
            $p->receiveMedia();
            // Toca menu principal ao atender
            $p->defineAudioFile('audios/menu_principal.wav');
            cli::pcl("Menu principal tocando...", "cyan");
        });

        // Processa teclas pressionadas
        $phone->onKeyPress(function ($digit, $peer) use ($phone, &$menuState, &$selectedOption) {
            cli::pcl("Tecla pressionada: $digit", "yellow");

            switch ($menuState) {
                case 'main':
                    switch ($digit) {
                        case '1':
                            cli::pcl("Opção 1: Suporte Técnico", "green");
                            $phone->defineAudioFile('audios/suporte_tecnico.wav');
                            $menuState = 'submenu_1';
                            break;
                        case '2':
                            cli::pcl("Opção 2: Vendas", "green");
                            $phone->defineAudioFile('audios/vendas.wav');
                            $menuState = 'submenu_2';
                            break;
                        case '3':
                            cli::pcl("Opção 3: Financeiro", "green");
                            $phone->defineAudioFile('audios/financeiro.wav');
                            $menuState = 'submenu_3';
                            break;
                        case '9':
                            cli::pcl("Opção 9: Falar com atendente", "green");
                            // Aqui você poderia transferir para número real
                            $phone->defineAudioFile('audios/transferindo.wav');
                            break;
                        case '0':
                            cli::pcl("Opção 0: Repetir menu", "cyan");
                            $phone->defineAudioFile('audios/menu_principal.wav');
                            break;
                        case '#':
                            cli::pcl("Finalizando chamada", "red");
                            $phone->bye();
                            break;
                        default:
                            cli::pcl("Opção inválida: $digit", "red");
                            $phone->defineAudioFile('audios/opcao_invalida.wav');
                    }
                    break;

                case 'submenu_1':
                case 'submenu_2':
                case 'submenu_3':
                    if ($digit === '*') {
                        cli::pcl("Voltando ao menu principal", "cyan");
                        $phone->defineAudioFile('audios/menu_principal.wav');
                        $menuState = 'main';
                    } elseif ($digit === '#') {
                        cli::pcl("Confirmando opção", "green");
                        $phone->defineAudioFile('audios/obrigado.wav');
                        // Aguarda 3 segundos e desliga
                        \libspech\Sip\interruptibleSleep(3, $phone->receiveBye);
                        $phone->bye();
                    }
                    break;
            }
        });

        $phone->onHangup(function ($p) {
            cli::pcl("Chamada encerrada pelo usuário", "red");
            $p->close();
        });

        // Aguarda chamada de entrada ou faz chamada de saída
        $phone->call('5511999887766');
    });
});
```

**Estrutura de áudios necessária:**
```
audios/
├── menu_principal.wav    # "Para suporte técnico tecle 1, vendas tecle 2..."
├── suporte_tecnico.wav   # "Você escolheu suporte técnico. Tecle * para voltar..."
├── vendas.wav           # "Você escolheu vendas. Tecle * para voltar..."
├── financeiro.wav       # "Você escolheu financeiro. Tecle * para voltar..."
├── transferindo.wav     # "Transferindo para um atendente..."
├── opcao_invalida.wav   # "Opção inválida. Tente novamente."
└── obrigado.wav         # "Obrigado por ligar. Tenha um bom dia!"
```

### 🎙️ Exemplo 3: Transcrição em Tempo Real com VAD

Processe áudio apenas quando há fala ativa, economizando recursos de transcrição:

```php
<?php
use libspech\Sip\trunkController;
use libspech\Cli\cli;

\Swoole\Runtime::enableCoroutine();
include 'plugins/autoloader.php';

\Swoole\Coroutine\run(function () {
    \Swoole\Coroutine::create(function () {
        $phone = new trunkController(
            getenv('SIP_USERNAME'),
            getenv('SIP_PASSWORD'),
            getenv('SIP_HOST')
        );

        $phone->register(2);
        $phone->mountLineCodecSDP('OPUS/48000/1');

        // Habilita VAD e gravação automática
        $phone->enableVAD();
        $phone->enableAudioRecording();

        // Buffer temporário apenas para segmentos de voz (processamento em tempo real)
        $voiceBuffer = '';
        $isCollectingVoice = false;
        $silenceFrames = 0;

        // Detecta mudança de estado de voz
        $phone->onVadChange(function ($isVoiceActive, $energy, $callId) use (&$isCollectingVoice, &$silenceFrames) {
            if ($isVoiceActive) {
                cli::pcl("🎤 Voz detectada! (Energia: $energy)", "bold_green");
                $isCollectingVoice = true;
                $silenceFrames = 0;
            } else {
                cli::pcl("🔇 Silêncio detectado (Energia: $energy)", "bold_red");
                $silenceFrames++;
            }
        });

        // Processa áudio em tempo real (apenas para segmentos de transcrição)
        $phone->onReceivePcm(function ($pcmData, $peer, $p) use (&$voiceBuffer, &$isCollectingVoice, &$silenceFrames) {
            // Coleta áudio apenas durante fala para transcrição em tempo real
            if ($isCollectingVoice) {
                $voiceBuffer .= $pcmData;

                // Após 5 frames de silêncio, processa o segmento
                if ($silenceFrames >= 5) {
                    cli::pcl("📝 Segmento de voz capturado: " . strlen($voiceBuffer) . " bytes", "cyan");

                    // Envie para API de transcrição em tempo real
                    // $transcription = transcribeAudio($voiceBuffer);
                    // cli::pcl("Transcrição: $transcription", "yellow");

                    // Limpa buffer para próximo segmento
                    $voiceBuffer = '';
                    $isCollectingVoice = false;
                    $silenceFrames = 0;
                }
            }
        });

        $phone->onAnswer(function ($p) {
            $p->receiveMedia();
            cli::pcl("📞 Chamada atendida - Transcrição ativa", "green");
        });

        $phone->onHangup(function ($p) use (&$voiceBuffer) {
            // Processa segmento final se houver
            if (strlen($voiceBuffer) > 0) {
                // $transcription = transcribeAudio($voiceBuffer);
                cli::pcl("📝 Segmento final processado", "green");
            }

            // Obtém gravação completa automaticamente
            $fullRecording = $p->getBuffer();
            $p->saveBufferToWavFile('recordings/call_full.wav', $fullRecording);
            cli::pcl("📼 Gravação completa salva", "green");

            $p->close();
        });

        $phone->call('5511999887766');
    });
});

cli::pcl("Processo de transcrição encerrado", "green");
```

**Integração com API de Transcrição:**

```php
// Exemplo de integração com Google Speech-to-Text
function transcribeAudio(string $pcmData): string {
    // Converte PCM para formato aceito pela API
    $base64Audio = base64_encode($pcmData);

    $apiKey = getenv('GOOGLE_API_KEY');
    $url = "https://speech.googleapis.com/v1/speech:recognize?key=$apiKey";

    $data = [
        'config' => [
            'encoding' => 'LINEAR16',
            'sampleRateHertz' => 48000,
            'languageCode' => 'pt-BR',
        ],
        'audio' => [
            'content' => $base64Audio
        ]
    ];

    // Faz requisição para API (pseudo-código)
    $response = httpPost($url, json_encode($data));
    $result = json_decode($response, true);

    return $result['results'][0]['alternatives'][0]['transcript'] ?? '';
}
```

## Testes e Validação

### Testando Manualmente

Execute o `example.php` para validar sua instalação:

```bash
# 1. Configure suas credenciais
cp .env.example .env
nano .env  # Edite com suas credenciais SIP

# 2. Execute o exemplo
php example.php

# 3. Verifique se:
# - Registro SIP foi bem-sucedido
# - Chamada foi realizada
# - Áudio foi gravado em rec.wav
```

### Validando Codecs

Teste diferentes codecs para verificar compatibilidade com seu provedor:

```bash
# Teste G.729 (baixa largura de banda)
php -r "require 'plugins/autoloader.php'; \$p = new libspech\Sip\trunkController('user','pass','host'); \$p->mountLineCodecSDP('G729/8000');"

# Teste Opus (alta qualidade)
php -r "require 'plugins/autoloader.php'; \$p = new libspech\Sip\trunkController('user','pass','host'); \$p->mountLineCodecSDP('OPUS/48000/1');"
```

### Depuração

Para depurar problemas de conexão ou áudio:

```php
<?php
use libspech\Cli\cli;

// Habilita logging detalhado
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Adiciona logs em todos os callbacks
$phone->onRinging(function ($p) {
    cli::pcl("[DEBUG] onRinging disparado", "cyan");
});

$phone->onAnswer(function ($p) {
    cli::pcl("[DEBUG] onAnswer disparado", "cyan");
    cli::pcl("[DEBUG] Codec negociado: " . $p->codecName, "cyan");
});

$phone->onReceivePcm(function ($pcmData, $peer, $p) {
    cli::pcl("[DEBUG] Áudio recebido: " . strlen($pcmData) . " bytes de {$peer['ip']}:{$peer['port']}", "cyan");
});
```

### Testes Automatizados

Atualmente não há testes automatizados no repositório. Contribuições são bem-vindas para:
- ✅ Testes unitários de parsing SIP/SDP
- ✅ Testes de codecs (encode/decode)
- ✅ Testes de fluxo de chamada simulada
- ✅ Testes de DTMF e VAD
- ✅ Testes de integração com servidores SIP

## Recursos Adicionais

### Documentação Oficial

- 📘 **[SIGNALING_ARRAYS.md](SIGNALING_ARRAYS.md)** - Entenda a estrutura interna dos arrays de sinalização SIP
- 📄 **[example.php](example.php)** - Exemplo completo e comentado (9 sessões)
- 🔐 **[SECURITY.md](SECURITY.md)** - Política de segurança e reporte de vulnerabilidades

### Links Úteis

- 🌐 **Website Oficial**: [https://spechshop.com](https://spechshop.com)
- 💻 **Repositório GitHub**: [https://github.com/spechshop/libspech](https://github.com/spechshop/libspech)
- 📦 **Releases (pcg729)**: [https://github.com/berzersks/pcg729/releases](https://github.com/berzersks/pcg729/releases)

### Protocolos e RFCs

Este projeto implementa os seguintes padrões:

- **RFC 3261** - SIP (Session Initiation Protocol)
- **RFC 3550** - RTP (Real-time Transport Protocol)
- **RFC 2833** - DTMF via RTP (telephone-event)
- **RFC 4566** - SDP (Session Description Protocol)
- **RFC 2617** - HTTP Digest Authentication (usado no SIP)

### Comunidade e Suporte

- 🐛 **Reportar Bug**: Abra uma issue no [GitHub](https://github.com/spechshop/libspech/issues)
- 💡 **Sugerir Feature**: Use as GitHub Discussions
- 🤝 **Contribuir**: Envie pull requests - toda ajuda é bem-vinda!
- 📧 **Contato**: Visite [spechshop.com](https://spechshop.com) para informações de contato

### Agradecimentos

- **Swoole Team** - Framework de corotinas PHP
- **Belledonne Communications** - BCG729 codec implementation
- **Xiph.Org Foundation** - Opus codec
- **Comunidade Open Source** - Por tornar projetos como este possíveis

## Licença

This project is licensed under the **Apache License 2.0**.

**Copyright © 2026 Lotus / berzersks**
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
