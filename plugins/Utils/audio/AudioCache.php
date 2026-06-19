<?php

namespace libspech\Audio;

use libspech\Cache\cache;

/**
 * Cache global/process-level para áudio pré-codificado por codec.
 *
 * Encapsula libspech\Cache\cache para evitar espalhar cache::get/define/unset
 * por todo o trunkController.
 *
 * Chaves internas:
 *  - libspechAudioEncodedCache    : payload encoded por chave
 *  - libspechAudioEncodedBuilding : marcação anti-stampede por chave
 */
class AudioCache
{
    private const KEY_ENCODED  = 'libspechAudioEncodedCache';
    private const KEY_BUILDING = 'libspechAudioEncodedBuilding';

    private const BUILDING_TIMEOUT = 30; // segundos
    private const CLEANUP_PROB     = 50; // 1 em N inserções

    public static function makeEncodedKey(
        string $audioFile,
        string $codec,
        int $frequency,
        int $channels,
        int $chunkSize,
        ?int $infoRate = null,
        ?int $infoChannels = null,
        ?int $bitDepth = null,
        $config = null
    ): string {
        $realPath = realpath($audioFile) ?: $audioFile;
        $mtime    = file_exists($audioFile) ? filemtime($audioFile) : 0;

        return md5(json_encode([
            'file'         => $realPath,
            'mtime'        => $mtime,
            'codec'        => strtoupper($codec),
            'frequency'    => $frequency,
            'channels'     => $channels,
            'chunkSize'    => $chunkSize,
            'infoRate'     => $infoRate,
            'infoChannels' => $infoChannels,
            'bitDepth'     => $bitDepth,
            'config'       => $config,
        ]));
    }

    public static function getEncoded(string $key): ?array
    {
        $bucket = cache::global()[self::KEY_ENCODED] ?? null;
        if (is_array($bucket) && isset($bucket[$key]) && is_array($bucket[$key])) {
            return $bucket[$key];
        }
        return null;
    }

    public static function setEncoded(string $key, array $payload): void
    {
        $now = time();
        $payload['createdAt'] = $payload['createdAt'] ?? $now;
        $payload['lastUsed']  = $now;

        cache::subDefine(self::KEY_ENCODED, $key, $payload);

        try {
            if (random_int(1, self::CLEANUP_PROB) === 1) {
                self::cleanup();
            }
        } catch (\Throwable $e) {
            // cleanup nunca pode quebrar a chamada
        }
    }

    public static function hasEncoded(string $key): bool
    {
        $bucket = cache::global()[self::KEY_ENCODED] ?? null;
        return is_array($bucket) && isset($bucket[$key]);
    }

    public static function markBuilding(string $key): bool
    {
        if (self::isBuilding($key)) {
            return false;
        }
        cache::subDefine(self::KEY_BUILDING, $key, time());
        return true;
    }

    public static function unmarkBuilding(string $key): void
    {
        try {
            cache::unset(self::KEY_BUILDING, $key);
        } catch (\Throwable $e) {
            // noop
        }
    }

    public static function isBuilding(string $key): bool
    {
        $bucket = cache::global()[self::KEY_BUILDING] ?? null;
        if (!is_array($bucket) || !isset($bucket[$key])) {
            return false;
        }
        $started = (int) $bucket[$key];
        if ($started <= 0 || (time() - $started) > self::BUILDING_TIMEOUT) {
            self::unmarkBuilding($key);
            return false;
        }
        return true;
    }

    public static function touchEncoded(string $key): void
    {
        $bucket = cache::get(self::KEY_ENCODED) ?? null;
        if (is_array($bucket) && isset($bucket[$key]) && is_array($bucket[$key])) {
            $bucket[$key]['lastUsed'] = time();
            cache::subDefine(self::KEY_ENCODED, $key, $bucket[$key]);
        }
    }

    public static function cleanup(int $maxItems = 32, int $ttlSeconds = 3600): void
    {
        $bucket = cache::global()[self::KEY_ENCODED] ?? null;
        if (!is_array($bucket) || empty($bucket)) {
            return;
        }

        $now     = time();
        $changed = false;

        // 1) TTL
        foreach ($bucket as $k => $item) {
            $lastUsed = $item['lastUsed'] ?? 0;
            if (($now - $lastUsed) >= $ttlSeconds && !self::isBuilding($k)) {
                unset($bucket[$k]);
                $changed = true;
            }
        }

        // 2) Limite de itens (LRU por lastUsed)
        if (count($bucket) > $maxItems) {
            uasort($bucket, fn($a, $b) => ($a['lastUsed'] ?? 0) <=> ($b['lastUsed'] ?? 0));
            foreach ($bucket as $k => $_) {
                if (count($bucket) <= $maxItems) {
                    break;
                }
                if (!self::isBuilding($k)) {
                    unset($bucket[$k]);
                    $changed = true;
                }
            }
        }

        if ($changed) {
            cache::define(self::KEY_ENCODED, $bucket);
        }
    }
}
