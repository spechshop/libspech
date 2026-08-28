<?php

declare(strict_types=1);

function section(string $title): void
{
    echo "\n=== {$title} ===\n";
}

function test(string $label, callable $fn): void
{
    echo "[TEST] {$label}\n";

    try {
        $result = $fn();

        if (is_string($result)) {
            echo "  OK string(" . strlen($result) . " bytes)\n";
        } else {
            echo "  OK ";
            var_dump($result);
        }
    } catch (Throwable $e) {
        echo "  ERROR " . get_class($e) . ": " . $e->getMessage() . "\n";
    }
}

section('INFO');

$gsm = new gsmChannel();
var_dump($gsm->info());

/*
 * GSM 06.10:
 *
 * 160 samples
 * 320 bytes PCM16LE
 * 33 bytes GSM
 * 20 ms
 */

section('ENCODE - VALID');

foreach ([320, 640, 960, 1280] as $bytes) {
    test("encode {$bytes} bytes PCM", function () use ($gsm, $bytes) {
        $pcm = str_repeat("\x00", $bytes);
        $encoded = $gsm->encode($pcm);

        echo "  input={$bytes}";
        echo " output=" . strlen($encoded);

        if ($encoded !== '') {
            printf(
                " first=0x%02X nibble=0x%X",
                ord($encoded[0]),
                (ord($encoded[0]) >> 4) & 0x0F
            );
        }

        echo "\n";

        return $encoded;
    });
}

section('ENCODE - INVALID SIZE');

$invalidPcmSizes = [
    0,
    1,
    2,
    100,
    159,
    160,
    318,
    319,
    321,
    322,
    639,
    641,
];

foreach ($invalidPcmSizes as $bytes) {
    test("encode invalid PCM size={$bytes}", function () use ($gsm, $bytes) {
        return $gsm->encode(str_repeat("\x00", $bytes));
    });
}

section('DECODE - BUILD VALID PAYLOADS');

$encoder = new gsmChannel();

$validEncodedFrames = [];

foreach ([1, 2, 3, 4] as $frames) {
    $pcm = str_repeat("\x00\x00", 160 * $frames);
    $encoded = $encoder->encode($pcm);

    $validEncodedFrames[$frames] = $encoded;

    echo sprintf(
        "%d frame(s): PCM=%d GSM=%d\n",
        $frames,
        strlen($pcm),
        strlen($encoded)
    );
}

section('DECODE - VALID');

$decoder = new gsmChannel();

foreach ($validEncodedFrames as $frames => $payload) {
    test("decode {$frames} frame(s)", function () use ($decoder, $payload) {
        $pcm = $decoder->decode($payload);

        echo "  GSM=" . strlen($payload);
        echo " PCM=" . strlen($pcm) . "\n";

        return $pcm;
    });
}

section('DECODE - INVALID LENGTH');

$referenceFrame = $validEncodedFrames[1];

$invalidGsmPayloads = [
    'empty' => '',
    '1_byte' => substr($referenceFrame, 0, 1),
    '32_bytes' => substr($referenceFrame, 0, 32),
    '34_bytes' => $referenceFrame . "\x00",
    '65_bytes' => $referenceFrame . substr($referenceFrame, 0, 32),
    '67_bytes' => $referenceFrame . $referenceFrame . "\x00",
    '98_bytes' => substr($validEncodedFrames[3], 0, 98),
    '100_bytes' => $validEncodedFrames[3] . "\x00",
];

foreach ($invalidGsmPayloads as $name => $payload) {
    test("decode invalid {$name} len=" . strlen($payload), function () use ($decoder, $payload) {
        return $decoder->decode($payload);
    });
}

section('DECODE - INVALID GSM SIGNATURE');

$badSignature = $referenceFrame;

// GSM RTP frame válido começa com nibble 0xD.
// Aqui destruímos somente esse nibble.
$badSignature[0] = chr(ord($badSignature[0]) & 0x0F);

test('decode frame with invalid GSM magic nibble', function () use ($decoder, $badSignature) {
    printf(
        "  first byte=0x%02X nibble=0x%X\n",
        ord($badSignature[0]),
        (ord($badSignature[0]) >> 4) & 0x0F
    );

    return $decoder->decode($badSignature);
});

section('STATE AFTER INVALID ENCODE');

$stateEncoder = new gsmChannel();

test('valid encode before invalid input', function () use ($stateEncoder) {
    return $stateEncoder->encode(
        str_repeat("\x00\x00", 160)
    );
});

test('invalid encode 319 bytes', function () use ($stateEncoder) {
    return $stateEncoder->encode(
        str_repeat("\x00", 319)
    );
});

test('valid encode after invalid input', function () use ($stateEncoder) {
    $encoded = $stateEncoder->encode(
        str_repeat("\x00\x00", 160)
    );

    echo "  output after error=" . strlen($encoded) . "\n";

    return $encoded;
});

section('STATE AFTER INVALID DECODE');

$stateEncoder2 = new gsmChannel();
$stateDecoder = new gsmChannel();

$validFrame = $stateEncoder2->encode(
    str_repeat("\x00\x00", 160)
);

test('valid decode before invalid input', function () use ($stateDecoder, $validFrame) {
    return $stateDecoder->decode($validFrame);
});

test('invalid decode 32 bytes', function () use ($stateDecoder, $validFrame) {
    return $stateDecoder->decode(
        substr($validFrame, 0, 32)
    );
});

test('valid decode after invalid input', function () use ($stateDecoder, $validFrame) {
    $pcm = $stateDecoder->decode($validFrame);

    echo "  output after error=" . strlen($pcm) . "\n";

    return $pcm;
});

section('LONG SEQUENTIAL STATE');

$longEncoder = new gsmChannel();
$longDecoder = new gsmChannel();

$iterations = 10000;

$encodedBytes = 0;
$decodedBytes = 0;

$start = hrtime(true);

for ($i = 0; $i < $iterations; $i++) {
    // Não usamos sempre silêncio absoluto para exercitar o estado do codec.
    $sample = (($i * 97) % 32768) - 16384;

    $frame = '';

    for ($s = 0; $s < 160; $s++) {
        $value = ($sample + ($s * 13)) & 0xFFFF;
        $frame .= pack('v', $value);
    }

    $encoded = $longEncoder->encode($frame);

    if (strlen($encoded) !== 33) {
        throw new RuntimeException(
            "Unexpected encoded size at iteration {$i}: " . strlen($encoded)
        );
    }

    if (((ord($encoded[0]) >> 4) & 0x0F) !== 0x0D) {
        throw new RuntimeException(
            "Invalid GSM magic nibble at iteration {$i}"
        );
    }

    $decoded = $longDecoder->decode($encoded);

    if (strlen($decoded) !== 320) {
        throw new RuntimeException(
            "Unexpected decoded size at iteration {$i}: " . strlen($decoded)
        );
    }

    $encodedBytes += strlen($encoded);
    $decodedBytes += strlen($decoded);
}

$elapsedNs = hrtime(true) - $start;
$elapsedMs = $elapsedNs / 1_000_000;

echo "iterations={$iterations}\n";
echo "encoded={$encodedBytes} bytes\n";
echo "decoded={$decodedBytes} bytes\n";
echo "elapsed=" . round($elapsedMs, 3) . " ms\n";
echo "per_frame=" . round($elapsedMs / $iterations, 6) . " ms\n";

section('CLOSE');

$closeTest = new gsmChannel();

test('first close', function () use ($closeTest) {
    return $closeTest->close();
});

test('second close', function () use ($closeTest) {
    return $closeTest->close();
});

test('encode after close', function () use ($closeTest) {
    return $closeTest->encode(
        str_repeat("\x00\x00", 160)
    );
});

test('decode after close', function () use ($closeTest, $referenceFrame) {
    return $closeTest->decode($referenceFrame);
});

section('DONE');