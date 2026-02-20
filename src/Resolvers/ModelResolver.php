<?php

namespace QuickBooks\SDK\Resolvers;

use QuickBooks\SDK\Resolvers\Contracts\CompanyResolverInterface;
use QuickBooks\SDK\Tenant\TenantContext;

class ModelResolver implements CompanyResolverInterface
{
    public function __construct(private TenantContext $tenantContext)
    {
    }

    public function all(): array
    {
        $companyClass = config('quickbooks.bridge_model');
        $model = new $companyClass();

        $query = $model->newQuery();

        $tenantId = $this->tenantContext->getTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $conditions = config('quickbooks.company_model.conditions', []);
        if (is_array($conditions)) {
            foreach ($conditions as $column => $value) {
                $query->where($column, $value);
            }
        }

        return $query->pluck('qb_company_id')->map(fn ($id) => (string) $id)->all();
    }

    public function has(string $qbCompanyId): bool
    {
        $companyClass = config('quickbooks.bridge_model');
        $model = new $companyClass();

        $query = $model->newQuery()->where('qb_company_id', $qbCompanyId);

        $tenantId = $this->tenantContext->getTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->exists();
    }
}
