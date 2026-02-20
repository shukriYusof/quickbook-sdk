<?php

namespace QuickBooks\SDK\Tests\Unit\TokenStores;

use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery;
use Mockery\MockInterface;
use QuickBooks\SDK\Tests\TestCase;
use QuickBooks\SDK\TokenStores\CacheTokenStore;

class CacheTokenStoreTest extends TestCase
{
    private MockInterface $cacheRepo;
    private CacheTokenStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheRepo = Mockery::mock(CacheRepository::class);

        $manager = Mockery::mock(CacheManager::class);
        $manager->shouldReceive('store')->andReturn($this->cacheRepo);

        $this->store = new CacheTokenStore($manager);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // put — TTL behaviour
    // -------------------------------------------------------------------------

    public function test_put_uses_ttl_when_refresh_token_expiry_is_future(): void
    {
        $expiresAt = Carbon::now()->addDays(90);
        $tokens    = $this->makeTokens($expiresAt);

        $this->cacheRepo
            ->shouldReceive('put')
            ->once()
            ->withArgs(function (string $key, array $value, int $ttl) use ($expiresAt): bool {
                // TTL must be positive and reasonably close to the diff in seconds.
                return $ttl > 0 && $ttl <= (int) Carbon::now()->diffInSeconds($expiresAt);
            });

        $this->store->put('company-abc', $tokens);

        $this->addToAssertionCount(1);
    }

    public function test_put_uses_forever_when_no_refresh_token_expiry(): void
    {
        $tokens = $this->makeTokens(null);

        $this->cacheRepo->shouldReceive('forever')->once();
        $this->cacheRepo->shouldNotReceive('put');

        $this->store->put('company-abc', $tokens);

        $this->addToAssertionCount(1);
    }

    public function test_put_uses_forever_when_refresh_token_already_expired(): void
    {
        $tokens = $this->makeTokens(Carbon::now()->subDay());

        $this->cacheRepo->shouldReceive('forever')->once();
        $this->cacheRepo->shouldNotReceive('put');

        $this->store->put('company-abc', $tokens);

        $this->addToAssertionCount(1);
    }

    public function test_put_accepts_string_expiry_date(): void
    {
        $tokens             = $this->makeTokens(null);
        $tokens['refresh_token_expires_at'] = Carbon::now()->addDays(30)->toIso8601String();

        $this->cacheRepo
            ->shouldReceive('put')
            ->once()
            ->withArgs(fn (string $k, array $v, int $ttl) => $ttl > 0);

        $this->store->put('company-abc', $tokens);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // get
    // -------------------------------------------------------------------------

    public function test_get_returns_null_when_key_not_found(): void
    {
        $this->cacheRepo->shouldReceive('get')->once()->andReturn(null);

        $this->assertNull($this->store->get('company-abc'));
    }

    public function test_get_returns_array_when_found(): void
    {
        $stored = $this->makeTokens(Carbon::now()->addHour());

        $this->cacheRepo->shouldReceive('get')->once()->andReturn($stored);

        $result = $this->store->get('company-abc');

        $this->assertIsArray($result);
        $this->assertEquals('access-token', $result['access_token']);
    }

    public function test_get_returns_null_when_value_is_not_an_array(): void
    {
        $this->cacheRepo->shouldReceive('get')->once()->andReturn('invalid-string');

        $this->assertNull($this->store->get('company-abc'));
    }

    // -------------------------------------------------------------------------
    // has / forget
    // -------------------------------------------------------------------------

    public function test_has_delegates_to_cache_store(): void
    {
        $this->cacheRepo->shouldReceive('has')->once()->andReturn(true);

        $this->assertTrue($this->store->has('company-abc'));
    }

    public function test_forget_delegates_to_cache_store(): void
    {
        $this->cacheRepo->shouldReceive('forget')->once();

        $this->store->forget('company-abc');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Cache key format
    // -------------------------------------------------------------------------

    public function test_key_uses_configured_prefix(): void
    {
        $this->cacheRepo
            ->shouldReceive('has')
            ->once()
            ->withArgs(fn (string $key) => str_starts_with($key, 'quickbooks_tokens:'));

        $this->store->has('company-abc');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function makeTokens(Carbon|null $refreshExpiresAt): array
    {
        return [
            'access_token'             => 'access-token',
            'refresh_token'            => 'refresh-token',
            'access_token_expires_at'  => Carbon::now()->addHour(),
            'refresh_token_expires_at' => $refreshExpiresAt,
        ];
    }
}
