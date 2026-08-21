<?php

declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
extra_bootstrap();

use libspech\Network\network;

/**
 * Regressão para a resolução DNS de network::resolveAddress().
 *
 * A partir do Swoole 6.2, com os hooks de coroutine ativos, dns_get_record()
 * passa pelo hook swoole_dns_get_record(), que inicializa o
 * Swoole\RemoteObject\Server (~/.swoole/remote-object-server.php) apenas para
 * resolver DNS. resolveAddress() foi migrada para a API DNS nativa de coroutine
 * (Swoole\Coroutine\System::gethostbyname). Estes testes garantem o contrato
 * público e que o daemon não é mais iniciado.
 */

function dnsAssertSame(mixed $expected, mixed $actual, string $message): void
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

function dnsAssertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dnsSwooleServerDir(): string
{
    $home = getenv('HOME');
    if (!is_string($home) || $home === '') {
        $home = sys_get_temp_dir();
    }
    return rtrim($home, '/') . '/.swoole';
}

// IP literal da família correta é retornado diretamente, sem consultar DNS.
dnsAssertSame('192.0.2.10', network::resolveAddress('192.0.2.10', 4), 'IPv4 literal correto retorna direto');
dnsAssertSame('2001:db8::10', network::resolveAddress('2001:db8::10', 6), 'IPv6 literal correto retorna direto');

// IP literal da família errada continua sendo rejeitado com erro claro.
$ipv4LiteralRejected = false;
try {
    network::resolveAddress('192.0.2.10', 6);
} catch (RuntimeException $exception) {
    $ipv4LiteralRejected = str_contains($exception->getMessage(), 'IPv6');
}
dnsAssertTrue($ipv4LiteralRejected, 'IPv4 literal é rejeitado quando se pede IPv6');

$ipv6LiteralRejected = false;
try {
    network::resolveAddress('2001:db8::10', 4);
} catch (RuntimeException $exception) {
    $ipv6LiteralRejected = str_contains($exception->getMessage(), 'IPv4');
}
dnsAssertTrue($ipv6LiteralRejected, 'IPv6 literal é rejeitado quando se pede IPv4');

// Hostname dual-stack (localhost) resolve para cada família fora de coroutine.
$resolvedIpv4 = network::resolveAddress('localhost', 4);
$resolvedIpv6 = network::resolveAddress('localhost', 6);
dnsAssertTrue((bool)filter_var($resolvedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4), 'localhost resolve como IPv4');
dnsAssertTrue((bool)filter_var($resolvedIpv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6), 'localhost resolve como IPv6');

// O endereço IPv6 retornado é normalizado para a forma canônica curta.
dnsAssertSame(inet_ntop(inet_pton($resolvedIpv6)), $resolvedIpv6, 'endereço IPv6 resolvido é normalizado');

// A mesma resolução funciona dentro de uma coroutine (caminho de produção).
Co\run(static function () use (&$coResolvedIpv4, &$coResolvedIpv6): void {
    $coResolvedIpv4 = network::resolveAddress('localhost', 4);
    $coResolvedIpv6 = network::resolveAddress('localhost', 6);
});
dnsAssertTrue((bool)filter_var($coResolvedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4), 'localhost resolve IPv4 dentro de coroutine');
dnsAssertTrue((bool)filter_var($coResolvedIpv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6), 'localhost resolve IPv6 dentro de coroutine');

// resolveAddressForSocket preserva o mapeamento dual-stack IPv4-mapped.
dnsAssertSame(
    '::ffff:192.0.2.10',
    network::resolveAddressForSocket('192.0.2.10', 6),
    'resolveAddressForSocket mapeia destino IPv4 para socket IPv6'
);
$socketIpv4RejectsIpv6 = false;
try {
    network::resolveAddressForSocket('2001:db8::10', 4);
} catch (RuntimeException $exception) {
    $socketIpv4RejectsIpv6 = str_contains($exception->getMessage(), 'IPv4');
}
dnsAssertTrue($socketIpv4RejectsIpv6, 'socket IPv4 não aceita destino IPv6');

// Objetivo central do issue: resolver DNS não pode iniciar o RemoteObject Server.
dnsAssertTrue(
    !is_dir(dnsSwooleServerDir()),
    'resolução DNS não inicializa ~/.swoole (Swoole\\RemoteObject\\Server)'
);

echo "OK: resolução DNS IPv4/IPv6, IP literal, família incorreta e isolamento do RemoteObject Server validados.\n";
