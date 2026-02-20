<?php

namespace QuickBooks\SDK\Tests\Unit\Resources;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use QuickBooks\SDK\QuickBooksClient;
use QuickBooks\SDK\Resources\Bill;
use QuickBooks\SDK\Resources\CreditMemo;
use QuickBooks\SDK\Resources\Employee;
use QuickBooks\SDK\Resources\Estimate;
use QuickBooks\SDK\Resources\Invoice;
use QuickBooks\SDK\Resources\Payment;
use QuickBooks\SDK\Resources\PurchaseOrder;
use QuickBooks\SDK\Resources\Vendor;

/**
 * Covers the resource-specific helper methods added on each typed class.
 */
class ResourcesTest extends TestCase
{
    private MockInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(QuickBooksClient::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Invoice
    // -------------------------------------------------------------------------

    public function test_invoice_overdue_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Invoice WHERE Balance > '0'")
            ->andReturn([]);

        (new Invoice($this->client))->overdue();

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Vendor
    // -------------------------------------------------------------------------

    public function test_vendor_active_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with('SELECT * FROM Vendor WHERE Active = true')
            ->andReturn([]);

        (new Vendor($this->client))->active();

        $this->addToAssertionCount(1);
    }

    public function test_vendor_find_by_name_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Vendor WHERE DisplayName = 'Acme Corp'")
            ->andReturn([]);

        (new Vendor($this->client))->findByName('Acme Corp');

        $this->addToAssertionCount(1);
    }

    public function test_vendor_find_by_name_escapes_special_characters(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->withArgs(function (string $query): bool {
                return str_contains($query, "\\'");
            })
            ->andReturn([]);

        (new Vendor($this->client))->findByName("O'Brien Supplies");

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Bill
    // -------------------------------------------------------------------------

    public function test_bill_unpaid_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Bill WHERE Balance > '0'")
            ->andReturn([]);

        (new Bill($this->client))->unpaid();

        $this->addToAssertionCount(1);
    }

    public function test_bill_for_vendor_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Bill WHERE VendorRef = '42'")
            ->andReturn([]);

        (new Bill($this->client))->forVendor('42');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // PurchaseOrder
    // -------------------------------------------------------------------------

    public function test_purchase_order_open_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM PurchaseOrder WHERE POStatus = 'Open'")
            ->andReturn([]);

        (new PurchaseOrder($this->client))->open();

        $this->addToAssertionCount(1);
    }

    public function test_purchase_order_for_vendor_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM PurchaseOrder WHERE VendorRef = '7'")
            ->andReturn([]);

        (new PurchaseOrder($this->client))->forVendor('7');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Estimate
    // -------------------------------------------------------------------------

    public function test_estimate_pending_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Estimate WHERE TxnStatus = 'Pending'")
            ->andReturn([]);

        (new Estimate($this->client))->pending();

        $this->addToAssertionCount(1);
    }

    public function test_estimate_accepted_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Estimate WHERE TxnStatus = 'Accepted'")
            ->andReturn([]);

        (new Estimate($this->client))->accepted();

        $this->addToAssertionCount(1);
    }

    public function test_estimate_for_customer_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Estimate WHERE CustomerRef = '5'")
            ->andReturn([]);

        (new Estimate($this->client))->forCustomer('5');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // CreditMemo
    // -------------------------------------------------------------------------

    public function test_credit_memo_unapplied_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM CreditMemo WHERE Balance > '0'")
            ->andReturn([]);

        (new CreditMemo($this->client))->unapplied();

        $this->addToAssertionCount(1);
    }

    public function test_credit_memo_for_customer_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM CreditMemo WHERE CustomerRef = '3'")
            ->andReturn([]);

        (new CreditMemo($this->client))->forCustomer('3');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Employee
    // -------------------------------------------------------------------------

    public function test_employee_active_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with('SELECT * FROM Employee WHERE Active = true')
            ->andReturn([]);

        (new Employee($this->client))->active();

        $this->addToAssertionCount(1);
    }

    public function test_employee_find_by_name_sends_correct_query(): void
    {
        $this->client
            ->shouldReceive('query')
            ->once()
            ->with("SELECT * FROM Employee WHERE DisplayName = 'Jane Smith'")
            ->andReturn([]);

        (new Employee($this->client))->findByName('Jane Smith');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Payment (inherited only — just verify no regression)
    // -------------------------------------------------------------------------

    public function test_payment_all_delegates_to_client_request(): void
    {
        $this->client
            ->shouldReceive('request')
            ->once()
            ->with('GET', 'payment')
            ->andReturn([]);

        (new Payment($this->client))->all();

        $this->addToAssertionCount(1);
    }
}
