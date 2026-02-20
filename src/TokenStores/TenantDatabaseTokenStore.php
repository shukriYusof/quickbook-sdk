<?php

namespace QuickBooks\SDK\TokenStores;

use Illuminate\Database\Eloquent\Builder;
use QuickBooks\SDK\Exceptions\CompanyNotFoundException;
use QuickBooks\SDK\Tenant\TenantContext;

class TenantDatabaseTokenStore extends DatabaseTokenStore
{
    public function __construct(private TenantContext $tenantContext)
    {
    }

    protected function query(): Builder
    {
        $query = $this->baseQuery();
        $tenantId = $this->tenantContext->getTenantId();

        if ($tenantId === null) {
            return $query;
        }

        $companyClass = config('quickbooks.bridge_model');
        $companyModel = new $companyClass();

        $companyTable = $companyModel->getTable();
        $tokenTable = $this->tokenModel()->getTable();

        return $query
            ->join($companyTable, $companyTable . '.qb_company_id', '=', $tokenTable . '.qb_company_id')
            ->where($companyTable . '.tenant_id', $tenantId)
            ->select($tokenTable . '.*');
    }

    public function put(string $qbCompanyId, array $tokens): void
    {
        $this->assertTenantAccess($qbCompanyId);
        $this->baseQuery()->updateOrCreate(
            ['qb_company_id' => $qbCompanyId],
            [
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'access_token_expires_at' => $tokens['access_token_expires_at'] ?? null,
                'refresh_token_expires_at' => $tokens['refresh_token_expires_at'] ?? null,
            ]
        );
    }

    public function forget(string $qbCompanyId): void
    {
        $this->assertTenantAccess($qbCompanyId);
        $this->baseQuery()->where('qb_company_id', $qbCompanyId)->delete();
    }

    private function assertTenantAccess(string $qbCompanyId): void
    {
        $tenantId = $this->tenantContext->getTenantId();
        if ($tenantId === null) {
            return;
        }

        $companyClass = config('quickbooks.bridge_model');
        $companyModel = new $companyClass();

        $company = $companyModel->newQuery()
            ->where('qb_company_id', $qbCompanyId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$company) {
            throw new CompanyNotFoundException('Company not found for the active tenant.');
        }
    }
}
