<?php

namespace QuickBooks\SDK\Resources;

class PurchaseOrder extends BaseResource
{
    protected function resourceName(): string
    {
        return 'purchaseorder';
    }

    /**
     * Purchase orders that are still open (not fully received/closed).
     *
     * @return array<string, mixed>
     */
    public function open(): array
    {
        return $this->query("SELECT * FROM PurchaseOrder WHERE POStatus = 'Open'");
    }

    /**
     * Purchase orders for a specific vendor.
     *
     * @return array<string, mixed>
     */
    public function forVendor(string $vendorId): array
    {
        $safe = str_replace(["\\", "'"], ["\\\\", "\\'"], $vendorId);

        return $this->query("SELECT * FROM PurchaseOrder WHERE VendorRef = '{$safe}'");
    }
}
