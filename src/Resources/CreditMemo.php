<?php

namespace QuickBooks\SDK\Resources;

class CreditMemo extends BaseResource
{
    protected function resourceName(): string
    {
        return 'creditmemo';
    }

    /**
     * Credit memos that have not yet been fully applied.
     *
     * @return array<string, mixed>
     */
    public function unapplied(): array
    {
        return $this->query("SELECT * FROM CreditMemo WHERE Balance > '0'");
    }

    /**
     * Credit memos for a specific customer.
     *
     * @return array<string, mixed>
     */
    public function forCustomer(string $customerId): array
    {
        $safe = str_replace(["\\", "'"], ["\\\\", "\\'"], $customerId);

        return $this->query("SELECT * FROM CreditMemo WHERE CustomerRef = '{$safe}'");
    }
}
