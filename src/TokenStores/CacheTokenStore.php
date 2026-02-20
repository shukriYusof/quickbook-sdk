<?php

namespace QuickBooks\SDK\TokenStores;

use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use QuickBooks\SDK\Contracts\TokenStoreInterface;

class CacheTokenStore implements TokenStoreInterface
{
    public function __construct(private CacheManager $cache)
    {
    }

    protected function store()
    {
        return $this->cache->store(config('quickbooks.cache_store'));
    }

    protected function key(string $qbCompanyId): string
    {
        $prefix = config('quickbooks.cache_prefix', 'quickbooks_tokens');

        return $prefix . ':' . $qbCompanyId;
    }

    public function get(string $qbCompanyId): ?array
    {
        $value = $this->store()->get($this->key($qbCompanyId));

        return is_array($value) ? $value : null;
    }

    public function put(string $qbCompanyId, array $tokens): void
    {
        $key       = $this->key($qbCompanyId);
        $expiresAt = $tokens['refresh_token_expires_at'] ?? null;

        // Derive TTL from the refresh token expiry so the cache entry naturally
        // expires when the token does, preventing stale tokens from lingering.
        $ttl = null;
        if ($expiresAt !== null) {
            $carbon = $expiresAt instanceof Carbon
                ? $expiresAt
                : Carbon::parse($expiresAt);

            $ttl = (int) Carbon::now()->diffInSeconds($carbon, false);
        }

        if ($ttl !== null && $ttl > 0) {
            $this->store()->put($key, $tokens, $ttl);
        } else {
            // No expiry known — fall back to storing indefinitely.
            $this->store()->forever($key, $tokens);
        }
    }

    public function forget(string $qbCompanyId): void
    {
        $this->store()->forget($this->key($qbCompanyId));
    }

    public function has(string $qbCompanyId): bool
    {
        return $this->store()->has($this->key($qbCompanyId));
    }
}
