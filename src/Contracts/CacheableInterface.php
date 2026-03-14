<?php

namespace App\Contracts;

interface CacheableInterface
{
    /**
     * Get the cache key prefix for this entity
     *
     * @return string
     */
    public function getCachePrefix(): string;

    /**
     * Get the cache TTL in seconds
     *
     * @return int
     */
    public function getCacheTTL(): int;

    /**
     * Invalidate all cached entries for this entity
     *
     * @return void
     */
    public function invalidateCache(): void;
}
