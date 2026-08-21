<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Network\network;
use libspech\Sip\sip;
use libspech\Sip\trunkController;

function sipIpAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s (esperado: %s, obtido: %s)',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function sipIpAssertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function sipIpWithoutConstructor(): trunkController
{
    return (new ReflectionClass(trunkController::class))->newInstanceWithoutConstructor();
}

// IPv4 continua sendo a configuração pública padrão, inclusive no sexto argumento.
$defaultTrunk = sipIpWithoutConstructor();
sipIpAssertSame(4, $defaultTrunk->getSipIpVersion(), 'SIP usa IPv4 por padrão');
$constructorParameters = (new ReflectionMethod(trunkController::class, '__construct'))->getParameters();
sipIpAssertSame(6, count($constructorParameters), 'construtor expõe o argumento SIP opcional');
sipIpAssertSame(4, $constructorParameters[5]->getDefaultValue(), 'sexto argumento mantém IPv4 como padrão');

// A seleção local nunca cruza famílias silenciosamente.
$selectLocalIp = new ReflectionMethod(network::class, 'selectLocalIp');
sipIpAssertSame(
    '192.0.2.10',
    $selectLocalIp->invoke(null, ['2001:db8::10', '192.0.2.10'], 4),
    'seleção local IPv4 ignora IPv6 anterior'
);
sipIpAssertSame(
    '2001:db8::10',
    $selectLocalIp->invoke(null, ['192.0.2.10', '2001:db8::10'], 6),
    'seleção local IPv6 ignora IPv4 anterior'
);
$missingIpv6Failed = false;
try {
    $selectLocalIp->invoke(null, ['192.0.2.10'], 6);
} catch (RuntimeException $exception) {
    $missingIpv6Failed = str_contains($exception->getMessage(), 'IPv6');
}
sipIpAssertTrue($missingIpv6Failed, 'IPv6 indisponível deve falhar com erro claro, sem fallback');

// Resolução por família: localhost representa o cenário dual-stack da validação.
$resolvedIpv4 = network::resolveAddress('localhost', 4);
$resolvedIpv6 = network::resolveAddress('localhost', 6);
sipIpAssertTrue((bool)filter_var($resolvedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4), 'localhost deve resolver via A');
sipIpAssertTrue((bool)filter_var($resolvedIpv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6), 'localhost deve resolver via AAAA');
$wrongLiteralFailed = false;
try {
    network::resolveAddress('192.0.2.10', 6);
} catch (RuntimeException $exception) {
    $wrongLiteralFailed = str_contains($exception->getMessage(), 'IPv6');
}
sipIpAssertTrue($wrongLiteralFailed, 'literal incompatível deve indicar a família solicitada');
sipIpAssertSame(
    '::ffff:192.0.2.10',
    network::resolveAddressForSocket('192.0.2.10', 6),
    'socket IPv6 dual-stack deve mapear destino IPv4 recebido em Contact'
);
$ipv4SocketRejectsIpv6 = false;
try {
    network::resolveAddressForSocket('2001:db8::10', 4);
} catch (RuntimeException $exception) {
    $ipv4SocketRejectsIpv6 = str_contains($exception->getMessage(), 'IPv4');
}
sipIpAssertTrue($ipv4SocketRejectsIpv6, 'socket IPv4 não deve aceitar destino IPv6');

// O factory real do trunk usa a família e o wildcard correspondentes.
$createSipSocket = new ReflectionMethod(trunkController::class, 'createSipSocket');
$socketTrunk = sipIpWithoutConstructor();
[$sipSocket4] = $createSipSocket->invoke($socketTrunk, 4);
[$sipSocket6] = $createSipSocket->invoke($socketTrunk, 6);
try {
    sipIpAssertSame('0.0.0.0', $sipSocket4->getsockname()['address'], 'socket SIP IPv4 faz bind no wildcard IPv4');
    sipIpAssertSame('::', $sipSocket6->getsockname()['address'], 'socket SIP IPv6 faz bind no wildcard IPv6');
    sipIpAssertSame(0, $sipSocket6->getOption(IPPROTO_IPV6, IPV6_V6ONLY), 'socket SIP IPv6 aceita destinos IPv4 mapeados');
} finally {
    $sipSocket4->close();
    $sipSocket6->close();
}

// O setter recria apenas o transporte SIP e mantém a referência RTP intacta.
$previousConfiguredIp = $GLOBALS['myIpAddress'] ?? null;
Co\run(static function () use ($createSipSocket): void {
    $GLOBALS['myIpAddress'] = '::1';
    $setterTrunk = sipIpWithoutConstructor();
    [$originalSipSocket, $originalSipPort] = $createSipSocket->invoke($setterTrunk, 4);
    $rtpMarker = new stdClass();

    $setterTrunk->username = '1000';
    $setterTrunk->host = '127.0.0.1';
    $setterTrunk->port = 9;
    $setterTrunk->sipLocalIp = '127.0.0.1';
    $setterTrunk->localIp = '192.0.2.50';
    $setterTrunk->socket = $originalSipSocket;
    $setterTrunk->socketPortListen = $originalSipPort;
    $setterTrunk->socketsList = [$originalSipSocket];
    $setterTrunk->rtpSocket = $rtpMarker;
    $setterTrunk->callId = 'setter-test';
    $setterTrunk->csq = 1;
    (new ReflectionProperty(trunkController::class, 'sipHostSource'))->setValue($setterTrunk, 'localhost');

    $setterTrunk->setSipIpVersion(6);
    try {
        sipIpAssertSame(6, $setterTrunk->getSipIpVersion(), 'setter ativa SIP IPv6');
        sipIpAssertSame('::', $setterTrunk->socket->getsockname()['address'], 'setter recria socket SIP em AF_INET6');
        sipIpAssertTrue($setterTrunk->socket !== $originalSipSocket, 'setter substitui o socket SIP');
        sipIpAssertSame('::1', $setterTrunk->host, 'setter resolve o destino SIP somente via IPv6');
        sipIpAssertTrue($setterTrunk->rtpSocket === $rtpMarker, 'setter não substitui o rtpSocket');
        sipIpAssertSame('192.0.2.50', $setterTrunk->localIp, 'setter não altera o IP de mídia');
    } finally {
        $setterTrunk->socket->close();
    }
});
if ($previousConfiguredIp === null) {
    unset($GLOBALS['myIpAddress']);
} else {
    $GLOBALS['myIpAddress'] = $previousConfiguredIp;
}

// Parsers Via/URI e renderização usam IPv6 sem confundir os ':' do endereço com a porta.
$via4 = sip::extractVia('SIP/2.0/UDP 192.168.1.10:5060;branch=abc');
$via6 = sip::extractVia('SIP/2.0/UDP [2001:db8::10]:5060;branch=abc');
sipIpAssertSame(
    ['transport' => 'UDP', 'address' => '192.168.1.10', 'port' => 5060, 'branch' => 'abc'],
    $via4,
    'parser Via IPv4'
);
sipIpAssertSame(
    ['transport' => 'UDP', 'address' => '2001:db8::10', 'port' => 5060, 'branch' => 'abc'],
    $via6,
    'parser Via IPv6'
);
sipIpAssertSame($via6, trunkController::extractVia('SIP/2.0/UDP [2001:db8::10]:5060;branch=abc'), 'parser do trunk delega ao parser SIP');
$via6WithoutPort = sip::extractVia('SIP/2.0/UDP [2001:db8::10];branch=abc');
sipIpAssertSame('2001:db8::10', $via6WithoutPort['address'], 'parser Via IPv6 sem porta preserva o endereço');
sipIpAssertSame(null, $via6WithoutPort['port'], 'parser Via IPv6 sem porta retorna porta nula');

$contact6 = sip::renderURI([
    'user' => '1000',
    'peer' => ['host' => '2001:db8::10', 'port' => 5070],
]);
sipIpAssertSame('<sip:1000@[2001:db8::10]:5070>', $contact6, 'Contact IPv6 usa brackets');
$parsedContact6 = sip::extractURI($contact6);
sipIpAssertSame('2001:db8::10', $parsedContact6['peer']['host'], 'parser URI remove brackets do host IPv6');
sipIpAssertSame('5070', $parsedContact6['peer']['port'], 'parser URI preserva porta IPv6');

// Builders SIP IPv6 usam sipLocalIp; o SDP continua exclusivamente no endereço de mídia IPv4.
$builder = sipIpWithoutConstructor();
$builder->username = '1000';
$builder->callerId = '1000';
$builder->host = '2001:db8::20';
$builder->port = 5060;
$builder->sipLocalIp = '2001:db8::5';
$builder->localIp = '192.168.1.50';
$builder->socketPortListen = 53000;
$builder->ssrc = 1234;
$builder->callId = 'sip-ip-test';
$builder->csq = 1;
$builder->mapLearn = [
    8 => ['rtpmap:8 PCMA/8000'],
    101 => ['rtpmap:101 telephone-event/8000', 'fmtp:101 0-16'],
];
$builder->rtpSocket = new class {
    public function getsockname(): array
    {
        return ['address' => '0.0.0.0', 'port' => 20000];
    }
};

$invite = $builder->modelInvite('2000');
sipIpAssertTrue(str_contains($invite['headers']['Via'][0], '[2001:db8::5]:53000'), 'Via do INVITE usa SIP IPv6');
sipIpAssertSame('<sip:1000@[2001:db8::5]:53000>', $invite['headers']['Contact'][0], 'Contact do INVITE usa SIP IPv6');
sipIpAssertSame('IN IP4 192.168.1.50', $invite['sdp']['c'][0], 'SDP mantém endereço de mídia IPv4');
sipIpAssertTrue(str_starts_with($invite['sdp']['m'][0], 'audio 20000 RTP/AVP'), 'porta RTP permanece a do rtpSocket');
sipIpAssertSame('0.0.0.0', $builder->rtpSocket->getsockname()['address'], 'RTP permanece no wildcard IPv4');

$register = $builder->modelRegister();
sipIpAssertTrue(str_contains($register['headers']['Via'][0], '[2001:db8::5]:53000'), 'Via do REGISTER usa SIP IPv6');
sipIpAssertSame('<sip:1000@[2001:db8::5]:53000>', $register['headers']['Contact'][0], 'Contact do REGISTER usa SIP IPv6');
sipIpAssertSame('REGISTER sip:[2001:db8::20] SIP/2.0', $register['methodForParser'], 'Request-URI do REGISTER usa brackets');

echo "OK: SIP IPv4/IPv6, DNS, parsers, brackets e isolamento RTP validados.\n";
