<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Check if API caching is enabled
     */
    public static function isEnabled(): bool
    {
        return config('cache.api_enabled', true);
    }

    /**
     * Get cache TTL in seconds (default: 5 minutes)
     */
    public static function getTTL(): int
    {
        return (int) config('cache.api_ttl', 300);
    }

    /**
     * Get or put a value in cache if enabled
     */
    public static function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!self::isEnabled()) {
            return $callback();
        }

        $ttl = $ttl ?? self::getTTL();

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Forget a cache key if enabled
     */
    public static function forget(string $key): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        return Cache::forget($key);
    }

    /**
     * Flush cache tags if enabled
     */
    public static function flushTags(array $tags): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $store = Cache::getStore();

        if (method_exists($store, 'tags')) {
            try {
                Cache::tags($tags)->flush();
                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Get tagged cache if enabled
     */
    public static function tags(array $tags)
    {
        if (!self::isEnabled()) {
            return null;
        }

        $store = Cache::getStore();

        if (method_exists($store, 'tags')) {
            try {
                return Cache::tags($tags);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
