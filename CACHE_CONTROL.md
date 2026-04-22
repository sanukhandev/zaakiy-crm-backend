# API Cache Control Documentation

## Overview

The ZaakiyCRM backend includes a configurable caching system that can be enabled or disabled via environment variables. This is useful for:

- **Development**: Disable caching to see changes immediately
- **Performance**: Enable caching to reduce database queries
- **Debugging**: Toggle caching on/off to isolate issues

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Enable or disable API caching (true/false)
API_CACHE_ENABLED=true

# Cache TTL in seconds (default: 300 = 5 minutes)
API_CACHE_TTL=300
```

### Default Settings

- **Caching**: Enabled by default (`true`)
- **TTL**: 5 minutes (`300` seconds)
- **Store**: Uses the configured `CACHE_STORE` (default: `file`)

## Usage

### Enable Caching (Production)

```env
API_CACHE_ENABLED=true
API_CACHE_TTL=300
```

### Disable Caching (Development/Debugging)

```env
API_CACHE_ENABLED=false
API_CACHE_TTL=300
```

When disabled, all API calls will **skip the cache** and query the database directly. This is useful when:
- Testing API changes
- Debugging slow queries
- Diagnosing cache-related issues

### Check Cache Status

Run the artisan command to see current cache settings:

```bash
php artisan cache:status
```

Example output:
```
=== API Cache Status ===

Caching Enabled: ✓ Yes
Cache Store: file
Cache TTL: 300 seconds (5.0 minutes)

To change settings, edit .env file:
  API_CACHE_ENABLED=true|false
  API_CACHE_TTL=300
```

## Implementation Details

### CacheHelper Class

The `App\Support\CacheHelper` class provides:

- `isEnabled()` - Check if caching is enabled
- `getTTL()` - Get TTL in seconds
- `remember()` - Get or put cached value
- `forget()` - Remove cached key
- `flushTags()` - Flush tagged cache

### Repositories

The `LeadRepository` uses `CacheHelper::bustCache()` to:
- Invalidate cache when leads are created
- Invalidate cache when leads are updated
- Invalidate cache when leads are deleted
- Invalidate cache when leads are moved to a new status

### API Endpoints Affected

All `GET` endpoints benefit from caching:

- `GET /v1/leads` - List leads (paginated)
- `GET /v1/pipeline` - Get pipeline stages
- `GET /v1/leads/{id}/activities` - List lead activities
- `GET /v1/me` - Current user info
- `GET /v1/session` - Session details

Write operations (`POST`, `PATCH`, `DELETE`) automatically invalidate the cache:

- `POST /v1/leads` - Creates lead + invalidates cache
- `PATCH /v1/leads/{id}` - Updates lead + invalidates cache
- `DELETE /v1/leads/{id}` - Deletes lead + invalidates cache
- `PATCH /v1/leads/{id}/move` - Moves lead + invalidates cache

## Performance Tips

### For Development

- **Disable caching**: Set `API_CACHE_ENABLED=false` for faster iterations
- **Short TTL**: Set `API_CACHE_TTL=60` (1 minute) for quicker cache refreshes

### For Production

- **Enable caching**: Set `API_CACHE_ENABLED=true`
- **Longer TTL**: Set `API_CACHE_TTL=3600` (1 hour) for better performance
- **Monitor**: Watch for stale data; adjust TTL based on update patterns

### Troubleshooting Slow APIs

If APIs are still slow with caching enabled:

1. **Check cache store**: Ensure `CACHE_STORE` is set to a fast driver (`redis` > `memcached` > `file` > `database`)
2. **Check database**: Run `EXPLAIN` on slow queries
3. **Check N+1 queries**: Ensure repositories don't load related data inefficiently
4. **Disable caching temporarily**: Set `API_CACHE_ENABLED=false` to measure database performance
5. **Review TTL**: Increase `API_CACHE_TTL` for frequently accessed data

## Examples

### Check if a Feature is Using Cache

Look for `CacheHelper` calls in the code:

```php
// In services/repositories
use App\Support\CacheHelper;

if (CacheHelper::isEnabled()) {
    // Use cache
} else {
    // Query database directly
}
```

### Add Caching to a New Endpoint

```php
// In a repository
use App\Support\CacheHelper;

public function getExpensiveData($id) {
    return CacheHelper::remember(
        "expensive_data_$id",
        fn() => $this->queryDatabase($id),
        3600 // 1 hour TTL
    );
}
```

## Monitoring

### Cache Hit Rate

To monitor cache effectiveness, add logging to `CacheHelper`:

```php
public static function remember($key, callable $callback, ?int $ttl = null) {
    if (!self::isEnabled()) {
        \Log::debug("Cache MISS (disabled): $key");
        return $callback();
    }

    $result = Cache::remember($key, $ttl ?? self::getTTL(), function() use ($callback) {
        \Log::debug("Cache MISS: $key");
        return $callback();
    });

    \Log::debug("Cache HIT: $key");
    return $result;
}
```

## FAQ

**Q: Will disabling cache break anything?**
A: No. All functionality works identically with caching disabled; it just queries the database every time.

**Q: How much faster is caching?**
A: Depends on the database and TTL. Typically 10-100x faster for cached reads vs. database queries.

**Q: Can I disable cache for specific endpoints?**
A: Yes, modify the service/repository to check for a request parameter or role before using cache.

**Q: What if cache gets stale?**
A: Cache is automatically invalidated on `POST`, `PATCH`, `DELETE` operations. You can also manually clear by:
  ```bash
  php artisan cache:clear
  ```

**Q: Can I use Redis for caching?**
A: Yes! Set `CACHE_STORE=redis` in `.env` and configure `REDIS_*` variables for better performance.
