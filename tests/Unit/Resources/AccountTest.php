<?php

namespace QuickBooks\SDK\Tests\Unit\Resources;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use QuickBooks\SDK\QuickBooksClient;
use QuickBooks\SDK\Resources\Account;

class AccountTest extends TestCase
{
    private MockInterface $client;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client  = Mockery::mock(QuickBooksClient::class);
        $this->account = new Account($this->client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // getByType — validation
    // -------------------------------------------------------------------------

    public function test_get_by_type_throws_on_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AccountType');

        $this->account->getByType('FakeType');
    }

    public function test_get_by_type_throws_on_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->account->getByType('');
    }

    public function test_get_by_type_throws_on_lowercase_valid_type(): void
    {
        // Comparison is strict — 'expense' should not match 'Expense'.
        $this->expectException(\InvalidArgumentException::class);
        $this->account->getByType('expense');
    }

    // -------------------------------------------------------------------------
    // getByType — every valid type is accepted
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('validAccountTypes')]
    public function test_get_by_type_accepts_all_valid_types(string $type): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->andReturn([]);

        // Should not throw.
        $this->account->getByType($type);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<array{string}>
     */
    public static function validAccountTypes(): array
    {
        return array_map(
            fn (string $type) => [$type],
            [
                'Bank',
                'Other Current Asset',
                'Fixed Asset',
                'Other Asset',
                'Accounts Receivable',
                'Equity',
                'Expense',
                'Other Expense',
                'Cost of Goods Sold',
                'Accounts Payable',
                'Credit Card',
                'Long Term Liability',
                'Other Current Liability',
                'Income',
                'Other Income',
            ]
        );
    }

    // -------------------------------------------------------------------------
    // getByType — query building
    // -------------------------------------------------------------------------

    public function test_get_by_type_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Account WHERE AccountType = 'Expense'")
            ->andReturn([]);

        $this->account->getByType('Expense');

        $this->addToAssertionCount(1);
    }

    public function test_exception_message_includes_invalid_type_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InvalidType');

        $this->account->getByType('InvalidType');
    }
}
