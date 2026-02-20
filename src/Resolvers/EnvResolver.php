<?php

namespace QuickBooks\SDK\Resolvers;

use QuickBooks\SDK\Resolvers\Contracts\CompanyResolverInterface;

class EnvResolver implements CompanyResolverInterface
{
    public function all(): array
    {
        $companies = config('quickbooks.companies', []);
        return array_values(array_filter(array_map('strval', $companies)));
    }

    public function has(string $qbCompanyId): bool
    {
        return in_array($qbCompanyId, $this->all(), true);
    }
}
