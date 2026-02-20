<?php

namespace QuickBooks\SDK\Resolvers\Contracts;

interface CompanyResolverInterface
{
    /**
     * @return array<int, string>
     */
    public function all(): array;

    public function has(string $qbCompanyId): bool;
}
