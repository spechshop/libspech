<?php

declare(strict_types=1);

use libspech\Rtp\rtpc;

ini_set('memory_limit', '1024M');

require_once __DIR__ . '/plugins/autoloader.php';

/*
 * Benchmark da libspech\Rtp\rtpc.
 *
 * Execute exatamente o mesmo script:
 *
 * 1. Antes da alteração da rtpc
 * 2. Depois da alteração da rtpc
 *
 * Exemplo:
 *
 * php benchmark-rtpc.php
 */

const ITERATIONS = 200_000;
const ROUNDS = 5;
const MEMORY_INSTANCES = 10_000;
const PAYLOAD_SIZE = 160;

function cpuTimeNs(): int
{
    $usage = getrusage();

    $user =
        ((int) ($usage['ru_utime.tv_sec'] ?? 0) * 1_000_000_000) +
        ((int) ($usage['ru_utime.tv_usec'] ?? 0) * 1_000);

    $system =
        ((int) ($usage['ru_stime.tv_sec'] ?? 0) * 1_000_000_000) +
        ((int) ($usage['ru_stime.tv_usec'] ?? 0) * 1_000);

    return $user + $system;
}

function median(array $values): float
{
    sort($values, SORT_NUMERIC);

    $count = count($values);

    if ($count === 0) {
        return 0.0;
    }

    $middle = intdiv($count, 2);

    if (($count % 2) === 0) {
        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    return $values[$middle];
}

function average(array $values): float
{
    if ($values === []) {
        return 0.0;
    }

    return array_sum($values) / count($values);
}

function formatNumber(float $value, int $decimals = 2): string
{
    return number_format(
        $value,
        $decimals,
        ',',
        '.'
    );
}

function buildTestPacket(): array
{
    $version = 2;
    $padding = 0;
    $extension = 0;
    $cc = 0;

    $marker = 1;
    $payloadType = 8;

    $sequence = 45678;
    $timestamp = 0x12345678;
    $ssrc = 0x23456789;

    $firstByte =
        (($version & 0x03) << 6) |
        (($padding & 0x01) << 5) |
        (($extension & 0x01) << 4) |
        ($cc & 0x0F);

    $secondByte =
        (($marker & 0x01) << 7) |
        ($payloadType & 0x7F);

    /*
     * Payload determinístico para permitir comparação byte a byte.
     */
    $payload = '';

    for ($i = 0; $i < PAYLOAD_SIZE; $i++) {
        $payload .= chr($i & 0xFF);
    }

    $header = pack(
        'CCnNN',
        $firstByte,
        $secondByte,
        $sequence,
        $timestamp,
        $ssrc
    );

    return [
        'packet' => $header . $payload,
        'payload' => $payload,

        'expected' => [
            'version' => $version,
            'padding' => $padding,
            'extension' => $extension,
            'cc' => $cc,
            'marker' => $marker,
            'payloadType' => $payloadType,
            'sequence' => $sequence,
            'timestamp' => $timestamp,
            'ssrc' => $ssrc,
        ],
    ];
}

function assertSameValue(
    string $name,
    mixed $expected,
    mixed $actual
): void {
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            sprintf(
                "FAIL: %s esperado=%s atual=%s\n",
                $name,
                var_export($expected, true),
                var_export($actual, true)
            )
        );

        exit(1);
    }
}

function validateRtpc(
    string $packet,
    string $payload,
    array $expected
): void {
    $rtp = new rtpc($packet);

    assertSameValue(
        'version',
        $expected['version'],
        $rtp->version
    );

    assertSameValue(
        'padding',
        $expected['padding'],
        $rtp->padding
    );

    assertSameValue(
        'extension',
        $expected['extension'],
        $rtp->extension
    );

    assertSameValue(
        'cc',
        $expected['cc'],
        $rtp->cc
    );

    assertSameValue(
        'marker',
        $expected['marker'],
        $rtp->marker
    );

    assertSameValue(
        'payloadType',
        $expected['payloadType'],
        $rtp->payloadType
    );

    assertSameValue(
        'sequence',
        $expected['sequence'],
        $rtp->sequence
    );

    assertSameValue(
        'timestamp',
        $expected['timestamp'],
        $rtp->timestamp
    );

    assertSameValue(
        'ssrc',
        $expected['ssrc'],
        $rtp->ssrc
    );

    assertSameValue(
        'payloadRaw',
        $payload,
        $rtp->payloadRaw
    );

    assertSameValue(
        'rawPacket',
        $packet,
        $rtp->rawPacket
    );

    assertSameValue(
        'getCodec()',
        $expected['payloadType'],
        $rtp->getCodec()
    );

    /*
     * Valida também a reconstrução RTP.
     */
    $rebuilt = $rtp->build($payload);

    assertSameValue(
        'build()',
        $packet,
        $rebuilt
    );

    echo "PASS: decodificacao funcional equivalente\n";
    echo "PASS: build RTP equivalente byte a byte\n";
}

function benchmarkConstructor(
    string $packet,
    int $iterations
): array {
    /*
     * Warmup.
     */
    for ($i = 0; $i < 10_000; $i++) {
        $rtp = new rtpc($packet);

        /*
         * Força uso das propriedades para evitar benchmark
         * completamente descartável.
         */
        $dummy =
            $rtp->sequence ^
            $rtp->timestamp ^
            $rtp->ssrc ^
            $rtp->payloadType;
    }

    unset($rtp);

    gc_collect_cycles();

    $wallStart = hrtime(true);
    $cpuStart = cpuTimeNs();

    $checksum = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $rtp = new rtpc($packet);

        $checksum ^=
            $rtp->sequence ^
            $rtp->timestamp ^
            $rtp->ssrc ^
            $rtp->payloadType;
    }

    $cpuEnd = cpuTimeNs();
    $wallEnd = hrtime(true);

    unset($rtp);

    return [
        'wall_ns' => $wallEnd - $wallStart,
        'cpu_ns' => $cpuEnd - $cpuStart,
        'checksum' => $checksum,
    ];
}

function benchmarkBuild(
    string $packet,
    string $payload,
    int $iterations
): array {
    $rtp = new rtpc($packet);

    /*
     * Warmup.
     */
    for ($i = 0; $i < 10_000; $i++) {
        $built = $rtp->build($payload);
    }

    $wallStart = hrtime(true);
    $cpuStart = cpuTimeNs();

    $checksum = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $built = $rtp->build($payload);

        $checksum ^= ord($built[0]);
        $checksum ^= strlen($built);
    }

    $cpuEnd = cpuTimeNs();
    $wallEnd = hrtime(true);

    return [
        'wall_ns' => $wallEnd - $wallStart,
        'cpu_ns' => $cpuEnd - $cpuStart,
        'checksum' => $checksum,
    ];
}

function benchmarkMemory(
    string $packet,
    int $instances
): float {
    gc_collect_cycles();

    $before = memory_get_usage(false);

    $objects = [];

    for ($i = 0; $i < $instances; $i++) {
        $objects[] = new rtpc($packet);
    }

    $after = memory_get_usage(false);

    $bytes = $after - $before;

    /*
     * Mantém referência até a medição terminar.
     */
    if (count($objects) !== $instances) {
        throw new RuntimeException(
            'Falha interna no benchmark de memoria'
        );
    }

    unset($objects);

    gc_collect_cycles();

    return $bytes / $instances;
}

function printBenchmark(
    string $name,
    array $rounds,
    int $iterations
): void {
    $wallPerPacket = [];
    $cpuPerPacket = [];

    foreach ($rounds as $round) {
        $wallPerPacket[] =
            $round['wall_ns'] / $iterations;

        $cpuPerPacket[] =
            $round['cpu_ns'] / $iterations;
    }

    $wallMedian = median($wallPerPacket);
    $wallAverage = average($wallPerPacket);

    $cpuMedian = median($cpuPerPacket);
    $cpuAverage = average($cpuPerPacket);

    $packetsPerSecond =
        $wallMedian > 0
            ? 1_000_000_000 / $wallMedian
            : 0;

    $cpuRatio =
        $wallMedian > 0
            ? ($cpuMedian / $wallMedian) * 100
            : 0;

    echo "\n";
    echo $name . "\n";
    echo str_repeat('-', 72) . "\n";

    printf(
        "Wall mediana       : %s ns/pacote\n",
        formatNumber($wallMedian, 1)
    );

    printf(
        "Wall media         : %s ns/pacote\n",
        formatNumber($wallAverage, 1)
    );

    printf(
        "CPU mediana        : %s ns/pacote\n",
        formatNumber($cpuMedian, 1)
    );

    printf(
        "CPU media          : %s ns/pacote\n",
        formatNumber($cpuAverage, 1)
    );

    printf(
        "CPU / wall         : %s %%\n",
        formatNumber($cpuRatio, 2)
    );

    printf(
        "Throughput mediano : %s pacotes/s\n",
        formatNumber($packetsPerSecond, 0)
    );
}

$test = buildTestPacket();

$packet = $test['packet'];
$payload = $test['payload'];
$expected = $test['expected'];

echo "============================================================\n";
echo "Benchmark libspech\\Rtp\\rtpc\n";
echo "============================================================\n";

printf(
    "PHP                : %s\n",
    PHP_VERSION
);

printf(
    "Packet             : %d bytes\n",
    strlen($packet)
);

printf(
    "Header             : 12 bytes\n"
);

printf(
    "Payload            : %d bytes\n",
    strlen($payload)
);

printf(
    "Iteracoes/rodada   : %s\n",
    number_format(ITERATIONS, 0, ',', '.')
);

printf(
    "Rodadas            : %d\n",
    ROUNDS
);

echo "\n";

validateRtpc(
    $packet,
    $payload,
    $expected
);

echo "\nMedindo memoria...\n";

$bytesPerObject = benchmarkMemory(
    $packet,
    MEMORY_INSTANCES
);

printf(
    "Memoria aproximada : %s bytes/instancia (%s instancias)\n",
    formatNumber($bytesPerObject, 1),
    number_format(
        MEMORY_INSTANCES,
        0,
        ',',
        '.'
    )
);

echo "\nBenchmark constructor...\n";

$constructorRounds = [];

for ($round = 1; $round <= ROUNDS; $round++) {
    gc_collect_cycles();

    $result = benchmarkConstructor(
        $packet,
        ITERATIONS
    );

    $constructorRounds[] = $result;

    printf(
        "Rodada %d/%d: %s ns/pacote\n",
        $round,
        ROUNDS,
        formatNumber(
            $result['wall_ns'] / ITERATIONS,
            1
        )
    );
}

printBenchmark(
    'new rtpc($packet)',
    $constructorRounds,
    ITERATIONS
);

echo "\nBenchmark build()...\n";

$buildRounds = [];

for ($round = 1; $round <= ROUNDS; $round++) {
    gc_collect_cycles();

    $result = benchmarkBuild(
        $packet,
        $payload,
        ITERATIONS
    );

    $buildRounds[] = $result;

    printf(
        "Rodada %d/%d: %s ns/pacote\n",
        $round,
        ROUNDS,
        formatNumber(
            $result['wall_ns'] / ITERATIONS,
            1
        )
    );
}

printBenchmark(
    'rtpc::build($payload)',
    $buildRounds,
    ITERATIONS
);

echo "\n============================================================\n";
echo "Fim\n";
echo "============================================================\n";