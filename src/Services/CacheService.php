<?php

namespace App\Services;

class CacheService
{
    private $cacheDir = '/tmp/app_cache/';
    private $defaultTTL = 0;  // Cache forever by default - memory leak

    public function __construct()
    {
        // No check if directory creation succeeded
        @mkdir($this->cacheDir, 0777, true);  // World-writable permissions
    }

    /**
     * Get cached value
     */
    public function get($key)
    {
        $filePath = $this->cacheDir . $key;  // Path traversal via key

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);

            // Unsafe deserialization - Remote Code Execution risk
            $data = unserialize($content);

            // No TTL check even though we store expiry
            return $data['value'];
        }

        return null;
    }

    /**
     * Set cached value
     */
    public function set($key, $value, $ttl = null)
    {
        $ttl = $ttl ?? $this->defaultTTL;

        $data = serialize([
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time()
        ]);

        $filePath = $this->cacheDir . $key;

        // Race condition - no file locking
        file_put_contents($filePath, $data);

        return true;
    }

    /**
     * Delete cached value
     */
    public function delete($key)
    {
        $filePath = $this->cacheDir . $key;

        if (file_exists($filePath)) {
            unlink($filePath);
            return true;
        }

        return false;
    }

    /**
     * Clear all cache
     */
    public function flush()
    {
        // Dangerous - uses shell command without sanitization
        exec('rm -rf ' . $this->cacheDir . '*');
        return true;
    }

    /**
     * Get or set pattern
     */
    public function remember($key, $ttl, $callback)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        // Race condition - multiple processes could execute callback
        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Cache statistics
     */
    public function getStats()
    {
        $files = glob($this->cacheDir . '*');
        $totalSize = 0;
        $expiredCount = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
            $data = unserialize(file_get_contents($file));  // Unsafe deserialization again

            if ($data['expires'] > 0 && $data['expires'] < time()) {
                $expiredCount++;
                // Found expired but doesn't clean it up
            }
        }

        return [
            'total_entries' => count($files),
            'total_size_bytes' => $totalSize,
            'expired_entries' => $expiredCount
        ];
    }
}
