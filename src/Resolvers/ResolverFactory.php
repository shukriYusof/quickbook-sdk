<?php

namespace QuickBooks\SDK\Resolvers;

use Illuminate\Contracts\Container\Container;
use QuickBooks\SDK\Resolvers\Contracts\CompanyResolverInterface;
use QuickBooks\SDK\Tenant\TenantContext;

class ResolverFactory
{
    public function __construct(private Container $container)
    {
    }

    public function make(?string $driver = null): CompanyResolverInterface
    {
        $driver = $driver ?: config('quickbooks.company_resolver', 'model');

        return match ($driver) {
            'env' => new EnvResolver(),
            'static' => new StaticResolver(config('quickbooks.companies', [])),
            'chain' => $this->makeChain(),
            'model' => new ModelResolver($this->container->make(TenantContext::class)),
            default => throw new \InvalidArgumentException("Unknown company resolver driver [{$driver}]."),
        };
    }

    protected function makeChain(): ChainResolver
    {
        $drivers = config('quickbooks.chain_resolvers', []);
        $resolvers = [];

        foreach ($drivers as $driver) {
            $resolvers[] = $this->make($driver);
        }

        return new ChainResolver($resolvers);
    }
}
