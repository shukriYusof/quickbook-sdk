<?php

namespace QuickBooks\SDK\Resources;

class Estimate extends BaseResource
{
    protected function resourceName(): string
    {
        return 'estimate';
    }

    /**
     * Estimates that are pending acceptance.
     *
     * @return array<string, mixed>
     */
    public function pending(): array
    {
        return $this->query("SELECT * FROM Estimate WHERE TxnStatus = 'Pending'");
    }

    /**
     * Estimates that have been accepted by the customer.
     *
     * @return array<string, mixed>
     */
    public function accepted(): array
    {
        return $this->query("SELECT * FROM Estimate WHERE TxnStatus = 'Accepted'");
    }

    /**
     * Estimates for a specific customer.
     *
     * @return array<string, mixed>
     */
    public function forCustomer(string $customerId): array
    {
        $safe = str_replace(["\\", "'"], ["\\\\", "\\'"], $customerId);

        return $this->query("SELECT * FROM Estimate WHERE CustomerRef = '{$safe}'");
    }
}
