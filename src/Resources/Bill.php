<?php

namespace QuickBooks\SDK\Resources;

class Bill extends BaseResource
{
    protected function resourceName(): string
    {
        return 'bill';
    }

    /**
     * Bills that have an outstanding balance.
     *
     * @return array<string, mixed>
     */
    public function unpaid(): array
    {
        return $this->query("SELECT * FROM Bill WHERE Balance > '0'");
    }

    /**
     * Bills for a specific vendor.
     *
     * @return array<string, mixed>
     */
    public function forVendor(string $vendorId): array
    {
        $safe = str_replace(["\\", "'"], ["\\\\", "\\'"], $vendorId);

        return $this->query("SELECT * FROM Bill WHERE VendorRef = '{$safe}'");
    }
}
