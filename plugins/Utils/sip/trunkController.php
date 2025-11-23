<?php

namespace libspech\Sip;


function secure_random_bytes(int $length): string
{
    try {
        return random_bytes($length);
    } catch (Exception $e) {
        $fs = '';
        for (; $length--;) $fs .= chr(mt_rand(0, 255));
        return $fs;
    }
}


function encodePcmToPcma(string $data): string
{
    if (strlen($data) % 2 !== 0) {
        $data .= "\x00";
    }

    $samples = unpack('s*', $data);
    $encoded = '';

    foreach ($samples as $sample) {
        $encoded .= chr(linear2alaw($sample));
    }

    return $encoded;
}

function encodePcmToPcmu(string $data): string
{
    $encoded = '';
    for ($i = 0; $i < strlen($data); $i += 2) {
        $sample = unpack('v', substr($data, $i, 2))[1];
        if ($sample > 32767) {
            $sample -= 65536;
        }
        $encoded .= chr(linear2ulaw($sample));
    }
    return $encoded;
}
