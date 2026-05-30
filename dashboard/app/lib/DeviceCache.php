<?php
namespace App;

class DeviceCache
{
    private const CACHE_FILE = '/tmp/genieacs-devices-cache.json';
    private const MAX_AGE = 120; // 2 minutes

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
     * Get devices from cache or fetch from GenieACS (with auto-cache)
     */
    public static function getDevices(\App\GenieACS $genieacs): array
    {
        $cached = self::get();
        if ($cached !== null) {
            return ['success' => true, 'data' => $cached];
        }

        // Cache miss — fetch from GenieACS
        $result = $genieacs->getDevices([], 0, 0);
        if ($result['success'] && is_array($result['data'])) {
            self::set($result['data']);
        }
        return $result;
    }
}
