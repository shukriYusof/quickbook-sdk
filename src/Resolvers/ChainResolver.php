<?php

namespace QuickBooks\SDK\Resolvers;

use QuickBooks\SDK\Resolvers\Contracts\CompanyResolverInterface;

class ChainResolver implements CompanyResolverInterface
{
    /**
     * @param array<int, CompanyResolverInterface> $resolvers
     */
    public function __construct(private array $resolvers)
    {
    }

    public function all(): array
    {
        $all = [];
        foreach ($this->resolvers as $resolver) {
            $all = array_merge($all, $resolver->all());
        }

        return array_values(array_unique(array_map('strval', $all)));
    }

    public function has(string $qbCompanyId): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->has($qbCompanyId)) {
                return true;
            }
        }

        return false;
    }
}
