<?php

namespace QuickBooks\SDK\Tests\Unit;

use Carbon\Carbon;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use QuickBooks\SDK\Contracts\TokenStoreInterface;
use QuickBooks\SDK\Exceptions\AuthenticationException;
use QuickBooks\SDK\OAuth\OAuth2Handler;
use QuickBooks\SDK\QuickBooksClient;
use QuickBooks\SDK\Resources\Account;
use QuickBooks\SDK\Resources\Bill;
use QuickBooks\SDK\Resources\CreditMemo;
use QuickBooks\SDK\Resources\Customer;
use QuickBooks\SDK\Resources\Employee;
use QuickBooks\SDK\Resources\Estimate;
use QuickBooks\SDK\Resources\Invoice;
use QuickBooks\SDK\Resources\Payment;
use QuickBooks\SDK\Resources\PurchaseOrder;
use QuickBooks\SDK\Resources\Vendor;
use QuickBooks\SDK\Tests\TestCase;

class QuickBooksClientTest extends TestCase
{
    private MockInterface $tokenStore;
    private MockInterface $oauth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenStore = Mockery::mock(TokenStoreInterface::class);
        $this->oauth      = Mockery::mock(OAuth2Handler::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeClient(): QuickBooksClient
    {
        return new QuickBooksClient(
            'qb-company-uuid',
            'realm-id-123',
            'sandbox',
            $this->tokenStore,
            $this->oauth,
            new NullLogger()
        );
    }

    // -------------------------------------------------------------------------
    // Token management
    // -------------------------------------------------------------------------

    public function test_get_tokens_throws_when_no_tokens_stored(): void
    {
        $this->tokenStore->shouldReceive('get')->once()->andReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No tokens found');

        $this->makeClient()->getTokens();
    }

    public function test_get_tokens_throws_when_refresh_token_is_expired(): void
    {
        $this->tokenStore->shouldReceive('get')->once()->andReturn([
            'access_token'             => 'at',
            'refresh_token'            => 'rt',
            'access_token_expires_at'  => Carbon::now()->subHour(),
            'refresh_token_expires_at' => Carbon::now()->subDay(),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Refresh token expired');

        $this->makeClient()->getTokens();
    }

    public function test_get_tokens_throws_when_refresh_token_is_empty_string(): void
    {
        $this->tokenStore->shouldReceive('get')->once()->andReturn([
            'access_token'             => 'at',
            'refresh_token'            => '',
            'access_token_expires_at'  => Carbon::now()->subHour(),
            'refresh_token_expires_at' => Carbon::now()->addDays(90),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Refresh token is missing');

        $this->makeClient()->getTokens();
    }

    public function test_get_tokens_returns_tokens_when_access_token_still_valid(): void
    {
        $tokens = [
            'access_token'             => 'valid-access-token',
            'refresh_token'            => 'valid-refresh-token',
            'access_token_expires_at'  => Carbon::now()->addHour(),
            'refresh_token_expires_at' => Carbon::now()->addDays(90),
        ];

        $this->tokenStore->shouldReceive('get')->once()->andReturn($tokens);

        $result = $this->makeClient()->getTokens();

        $this->assertEquals('valid-access-token', $result['access_token']);
    }

    public function test_get_tokens_refreshes_when_access_token_expired(): void
    {
        $originalTokens = [
            'access_token'             => 'expired-at',
            'refresh_token'            => 'valid-rt',
            'access_token_expires_at'  => Carbon::now()->subMinute(),
            'refresh_token_expires_at' => Carbon::now()->addDays(90),
        ];

        $refreshedTokens = [
            'access_token'             => 'new-access-token',
            'refresh_token'            => 'new-refresh-token',
            'access_token_expires_at'  => Carbon::now()->addHour(),
            'refresh_token_expires_at' => Carbon::now()->addDays(90),
            'realm_id'                 => 'realm-id-123',
        ];

        $this->tokenStore->shouldReceive('get')->once()->andReturn($originalTokens);
        $this->oauth->shouldReceive('refreshToken')
            ->once()
            ->with('valid-rt', 'realm-id-123')
            ->andReturn($refreshedTokens);
        $this->tokenStore->shouldReceive('put')->once();

        $result = $this->makeClient()->getTokens();

        $this->assertEquals('new-access-token', $result['access_token']);
    }

    public function test_get_tokens_caches_result_for_subsequent_calls(): void
    {
        $tokens = [
            'access_token'             => 'at',
            'refresh_token'            => 'rt',
            'access_token_expires_at'  => Carbon::now()->addHour(),
            'refresh_token_expires_at' => Carbon::now()->addDays(90),
        ];

        // Should only hit the store once even on two calls.
        $this->tokenStore->shouldReceive('get')->once()->andReturn($tokens);

        $client = $this->makeClient();
        $client->getTokens();
        $client->getTokens();

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Identifiers
    // -------------------------------------------------------------------------

    public function test_get_company_id_returns_correct_value(): void
    {
        $this->assertEquals('qb-company-uuid', $this->makeClient()->getCompanyId());
    }

    public function test_get_realm_id_returns_correct_value(): void
    {
        $this->assertEquals('realm-id-123', $this->makeClient()->getRealmId());
    }

    // -------------------------------------------------------------------------
    // Resource accessors
    // -------------------------------------------------------------------------

    public function test_resource_accessors_return_correct_types(): void
    {
        $client = $this->makeClient();

        $this->assertInstanceOf(Invoice::class, $client->invoices());
        $this->assertInstanceOf(Customer::class, $client->customers());
        $this->assertInstanceOf(Payment::class, $client->payments());
        $this->assertInstanceOf(Account::class, $client->accounts());
        $this->assertInstanceOf(Vendor::class, $client->vendors());
        $this->assertInstanceOf(Bill::class, $client->bills());
        $this->assertInstanceOf(PurchaseOrder::class, $client->purchaseOrders());
        $this->assertInstanceOf(Estimate::class, $client->estimates());
        $this->assertInstanceOf(CreditMemo::class, $client->creditMemos());
        $this->assertInstanceOf(Employee::class, $client->employees());
    }

    public function test_resource_accessors_return_new_instances_each_call(): void
    {
        $client = $this->makeClient();

        $this->assertNotSame($client->invoices(), $client->invoices());
        $this->assertNotSame($client->customers(), $client->customers());
    }
}
