<?php

namespace libspech\Network;

use libspech\Cache\cache;
use libspech\Cli\cli;
use Swoole\Coroutine\Socket;

class network
{
    private static int $minPort = 10000;
    private static int $maxPort = 62000;

    public static function getLocalIp(int $ipVersion = 4): string
    {
        $family = self::socketFamily($ipVersion);
        $configuredAddress = cache::get('myIpAddress');
        $addresses = swoole_get_local_ip($family);

        if (is_string($configuredAddress) && $configuredAddress !== '') {
            array_unshift($addresses, $configuredAddress);
        }
        return self::selectLocalIp($addresses, $ipVersion);
    }

    private static function selectLocalIp(array $addresses, int $ipVersion): string
    {
        self::socketFamily($ipVersion);
        $filterFlag = $ipVersion === 4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
        foreach ($addresses as $localAddress) {
            if (filter_var($localAddress, FILTER_VALIDATE_IP, $filterFlag)) {
                return $localAddress;
            }
        }

        throw new \RuntimeException("Nenhum endereço IPv{$ipVersion} local disponível");
    }

    public static function getLocalIpv4(): string
    {
        return self::getLocalIp(4);
    }

    public static function getLocalIpv6(): string
    {
        return self::getLocalIp(6);
    }

    public static function isPrivateIp(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $privateRanges = ["10.0.0.0|10.255.255.255", "172.16.0.0|172.31.255.255", "192.168.0.0|192.168.255.255"];
            foreach ($privateRanges as $range) {
                [$start, $end] = explode("|", $range);
                if (ip2long($ip) >= ip2long($start) && ip2long($ip) <= ip2long($end)) {
                    return true;
                }
            }
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (str_starts_with($ip, "fc") || str_starts_with($ip, "fd")) {
                return true;
            }
        }
        return false;
    }

    public static function isPublicIp(?string $ip): bool
    {
        return !self::isPrivateIp($ip);
    }

    public static function getFreePort($type = 'udp', int $ipVersion = 4): ?int
    {
        $maxAttempts = 100;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $port = rand(self::$minPort, self::$maxPort);
            if (self::isPortAvailable($port, $type, $ipVersion)) {
                return $port;
            }
        }
        print cli::cl('red', 'Could not find a free port, retrying...');
        return self::getFreePort($type, $ipVersion);
    }

    public static function isPortAvailable(int $port, $type = 'udp', int $ipVersion = 4): bool
    {
        if (strtolower($type) == 'udp') $typeSock=SOCK_DGRAM;
        elseif (strtolower($type) == 'tcp') $typeSock=SOCK_STREAM;
        else $typeSock=SOCK_RAW;
        $family = self::socketFamily($ipVersion);
        $socket = new \Swoole\Coroutine\Socket($family, $typeSock, 0);
        $bindAddress = $ipVersion === 4 ? '0.0.0.0' : '::';
        $result = $socket->bind($type === 'udp' ? $bindAddress : '', $port);
        $socket->close();
        return $result;
    }

    /** retorna o ip resolvido caso seja 127.0.0.1 por exemplo, retornará 10.0.2.6 */
    public static function resolveAddress(mixed $address, int $ipVersion = 4): string
    {
        self::socketFamily($ipVersion);
        $host = self::extractHost($address);
        $filterFlag = $ipVersion === 4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
        $otherFilterFlag = $ipVersion === 4 ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;

        if (filter_var($host, FILTER_VALIDATE_IP, $filterFlag)) {
            return $host;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, $otherFilterFlag)) {
            throw new \RuntimeException("O host {$host} não pertence à família IPv{$ipVersion} solicitada");
        }

        $recordType = $ipVersion === 4 ? DNS_A : DNS_AAAA;
        $recordKey = $ipVersion === 4 ? 'ip' : 'ipv6';
        $records = @dns_get_record($host, $recordType);

        if (is_array($records)) {
            foreach ($records as $record) {
                $resolvedAddress = $record[$recordKey] ?? null;
                if (is_string($resolvedAddress) && filter_var($resolvedAddress, FILTER_VALIDATE_IP, $filterFlag)) {
                    return $resolvedAddress;
                }
            }
        }

        $recordName = $ipVersion === 4 ? 'A' : 'AAAA';
        throw new \RuntimeException("Não foi possível resolver {$host} como IPv{$ipVersion} (registro {$recordName} ausente)");
    }

    public static function extractHost(mixed $address): string
    {
        $address = trim((string)$address);
        if ($address === '') {
            throw new \InvalidArgumentException('O host não pode ser vazio');
        }

        if (str_starts_with($address, '[')) {
            $closingBracket = strpos($address, ']');
            if ($closingBracket !== false) {
                return substr($address, 1, $closingBracket - 1);
            }
        }

        if (filter_var($address, FILTER_VALIDATE_IP)) {
            return $address;
        }

        $url = str_contains($address, '://') ? $address : "//{$address}";
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return trim($host, '[]');
        }

        $host = preg_replace('#/.*$#', '', $address);
        if (is_string($host) && $host !== '') {
            return trim($host, '[]');
        }

        throw new \InvalidArgumentException("Host inválido: {$address}");
    }

    public static function socketFamily(int $ipVersion): int
    {
        return match ($ipVersion) {
            4 => AF_INET,
            6 => AF_INET6,
            default => throw new \InvalidArgumentException('A versão IP do SIP deve ser 4 ou 6'),
        };
    }
}
