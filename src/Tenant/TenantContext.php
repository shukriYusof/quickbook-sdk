<?php

namespace QuickBooks\SDK\Tenant;

class TenantContext
{
    private ?string $tenantId = null;

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
