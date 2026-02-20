<?php

namespace QuickBooks\SDK\Tests\Unit\Resources;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use QuickBooks\SDK\QuickBooksClient;
use QuickBooks\SDK\Resources\Customer;

class CustomerTest extends TestCase
{
    private MockInterface $client;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client   = Mockery::mock(QuickBooksClient::class);
        $this->customer = new Customer($this->client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // findByEmail — validation
    // -------------------------------------------------------------------------

    public function test_find_by_email_throws_on_plaintext_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->customer->findByEmail('not-an-email');
    }

    public function test_find_by_email_throws_on_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->customer->findByEmail('');
    }

    public function test_find_by_email_throws_on_missing_tld(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->customer->findByEmail('user@domain');
    }

    public function test_find_by_email_throws_on_injection_attempt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // An injection string is not a valid email — it should be rejected.
        $this->customer->findByEmail("' OR '1'='1");
    }

    // -------------------------------------------------------------------------
    // findByEmail — query building
    // -------------------------------------------------------------------------

    public function test_find_by_email_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Customer WHERE PrimaryEmailAddr = 'user@example.com'")
            ->andReturn([]);

        $result = $this->customer->findByEmail('user@example.com');

        $this->assertIsArray($result);
    }

    public function test_find_by_email_rejects_backslash_in_local_part(): void
    {
        // Backslash in an unquoted local part is not RFC-valid — filter_var rejects it,
        // so the input never reaches the escaping/query stage.
        $this->expectException(\InvalidArgumentException::class);

        $this->customer->findByEmail('user\\name@example.com');
    }

    // -------------------------------------------------------------------------
    // active
    // -------------------------------------------------------------------------

    public function test_active_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with('SELECT * FROM Customer WHERE Active = true')
            ->andReturn([['Id' => '1']]);

        $result = $this->customer->active();

        $this->assertIsArray($result);
    }
}
