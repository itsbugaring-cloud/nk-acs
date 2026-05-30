<?php
namespace App;

class DeviceCache
{
    private const CACHE_FILE = '/tmp/genieacs-devices-cache.json';
    private const LOCK_FILE = '/tmp/genieacs-devices-cache.lock';
    private const MAX_AGE = 120; // 2 minutes
    private const LOCK_WAIT_TIMEOUT = 30; // seconds to wait for another process

    public static function get(): ?array
    {
        if (!file_exists(self::CACHE_FILE)) {
            return null;
        }
        $mtime = filemtime(self::CACHE_FILE);
        if ((time() - $mtime) > self::MAX_AGE) {
            return null;
        }
        $data = @file_get_contents(self::CACHE_FILE);
        if ($data === false) {
            return null;
        }
        return json_decode($data, true);
    }

    public static function set(array $devices): void
    {
        $tmp = self::CACHE_FILE . '.tmp';
        file_put_contents($tmp, json_encode($devices, JSON_UNESCAPED_SLASHES));
        rename($tmp, self::CACHE_FILE);
    }

    /**
     * Get devices from cache or fetch from GenieACS (with file lock)
     * Only ONE process fetches at a time; others wait for the cache to fill.
     */
    public static function getDevices(\App\GenieACS $genieacs): array
    {
        // 1. Try cache first (fast path)
        $cached = self::get();
        if ($cached !== null) {
            return ['success' => true, 'data' => $cached];
        }

        // 2. Acquire exclusive lock — only 1 process fetches
        $lockFp = fopen(self::LOCK_FILE, 'c');
        if ($lockFp === false) {
            // Fallback: fetch without lock
            return self::fetchAndCache($genieacs);
        }

        if (flock($lockFp, LOCK_EX | LOCK_NB)) {
            // We got the lock — we are the fetcher
            try {
                // Double-check cache (another process may have filled it)
                $cached = self::get();
                if ($cached !== null) {
                    return ['success' => true, 'data' => $cached];
                }
                return self::fetchAndCache($genieacs);
            } finally {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
            }
        } else {
            // Another process is fetching — wait for cache to appear
            fclose($lockFp);
            return self::waitForCache($genieacs);
        }
    }

    private static function fetchAndCache(\App\GenieACS $genieacs): array
    {
        $result = $genieacs->getDevices([], 0, 0);
        if ($result['success'] && is_array($result['data'])) {
            self::set($result['data']);
        }
        return $result;
    }

    private static function waitForCache(\App\GenieACS $genieacs): array
    {
        $deadline = time() + self::LOCK_WAIT_TIMEOUT;
        while (time() < $deadline) {
            usleep(500000); // 0.5s
            $cached = self::get();
            if ($cached !== null) {
                return ['success' => true, 'data' => $cached];
            }
        }
        // Timeout — try fetching ourselves as last resort
        return self::fetchAndCache($genieacs);
    }
}
