<?php

namespace QuickBooks\SDK\Resolvers;

use QuickBooks\SDK\Resolvers\Contracts\CompanyResolverInterface;

class StaticResolver implements CompanyResolverInterface
{
    /**
     * @param array<int, string> $companies
     */
    public function __construct(private array $companies = [])
    {
    }

    public function all(): array
    {
        return array_values(array_filter(array_map('strval', $this->companies)));
    }

    public function has(string $qbCompanyId): bool
    {
        return in_array($qbCompanyId, $this->all(), true);
    }
}
