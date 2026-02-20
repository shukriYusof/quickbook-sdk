<?php

namespace QuickBooks\SDK;

use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\DB;
use QuickBooks\SDK\Models\QuickBooksCompany;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use QuickBooks\SDK\Concerns\ParsesDate;
use QuickBooks\SDK\Contracts\TokenStoreInterface;
use QuickBooks\SDK\Exceptions\AuthenticationException;
use QuickBooks\SDK\Exceptions\CompanyNotFoundException;
use QuickBooks\SDK\OAuth\OAuth2Handler;
use QuickBooks\SDK\Resolvers\ResolverFactory;
use QuickBooks\SDK\Resolvers\Contracts\CompanyResolverInterface;
use QuickBooks\SDK\Tenant\TenantContext;

class QuickBooksManager
{
    use ParsesDate;

    /** @var array<string, QuickBooksClient> */
    private array $clients = [];
    private ?CompanyResolverInterface $resolver = null;

    public function __construct(
        private ConfigRepository $config,
        private ResolverFactory $resolverFactory,
        private TokenStoreInterface $tokenStore,
        private OAuth2Handler $oauth,
        private TenantContext $tenantContext,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function company(string $qbCompanyId): QuickBooksClient
    {
        if (!$this->resolver()->has($qbCompanyId)) {
            throw new CompanyNotFoundException("Unknown QuickBooks company ID [{$qbCompanyId}].");
        }

        if (isset($this->clients[$qbCompanyId])) {
            return $this->clients[$qbCompanyId];
        }

        $company = $this->resolveCompanyRecord($qbCompanyId);

        if (!$company->qb_realm_id) {
            throw new AuthenticationException('Company is not connected to QuickBooks.');
        }

        $environment = $company->environment ?: $this->config->get('quickbooks.environment', 'production');

        $client = new QuickBooksClient(
            $qbCompanyId,
            $company->qb_realm_id,
            $environment,
            $this->tokenStore,
            $this->oauth,
            $this->logger
        );

        $this->clients[$qbCompanyId] = $client;

        return $client;
    }

    public function client(): QuickBooksClient
    {
        $defaultCompany = $this->config->get('quickbooks.default_company');

        if (!$defaultCompany) {
            throw new CompanyNotFoundException('No default company configured.');
        }

        return $this->company($defaultCompany);
    }

    public function getAuthorizationUrl(string $qbCompanyId, ?array $scopes = null): string
    {
        $this->ensureCompanyExists($qbCompanyId);

        return $this->oauth->getAuthorizationUrl($qbCompanyId, $scopes);
    }

    public function handleCallback(string $code, string $realmId, string $state): QuickBooksClient
    {
        $qbCompanyId = $this->oauth->extractCompanyId($state);

        $this->logger->info('QuickBooks Manager: handling OAuth callback.', [
            'qb_company_id' => $qbCompanyId,
            'realm_id'      => $realmId,
        ]);

        // Exchange code outside the transaction — this is an external HTTP call.
        $tokens = $this->oauth->exchangeCode($code, $realmId);

        DB::transaction(function () use ($qbCompanyId, $tokens, $realmId): void {
            $this->tokenStore->put($qbCompanyId, $tokens);

            $company                  = $this->resolveCompanyRecord($qbCompanyId);
            $company->qb_realm_id     = $realmId;
            $company->environment     = $this->config->get('quickbooks.environment', 'production');
            $company->connected_at    = Carbon::now();
            $company->disconnected_at = null;
            $company->save();
        });

        $this->logger->info('QuickBooks Manager: company connected successfully.', [
            'qb_company_id' => $qbCompanyId,
            'realm_id'      => $realmId,
        ]);

        return $this->company($qbCompanyId);
    }

    public function disconnectCompany(string $qbCompanyId): void
    {
        $this->ensureCompanyExists($qbCompanyId);

        $this->logger->info('QuickBooks Manager: disconnecting company.', [
            'qb_company_id' => $qbCompanyId,
        ]);

        // Revoke the token outside the transaction — external HTTP call.
        $tokens = $this->tokenStore->get($qbCompanyId);
        if ($tokens && !empty($tokens['refresh_token'])) {
            $this->oauth->revokeToken($tokens['refresh_token']);
        }

        DB::transaction(function () use ($qbCompanyId): void {
            $this->tokenStore->forget($qbCompanyId);

            $company                  = $this->resolveCompanyRecord($qbCompanyId);
            $company->disconnected_at = Carbon::now();
            $company->save();
        });

        unset($this->clients[$qbCompanyId]);

        $this->logger->info('QuickBooks Manager: company disconnected.', [
            'qb_company_id' => $qbCompanyId,
        ]);
    }

    public function isConnected(string $qbCompanyId): bool
    {
        $tokens = $this->tokenStore->get($qbCompanyId);

        if (!$tokens) {
            return false;
        }

        $refreshExpiresAt = $this->parseDate($tokens['refresh_token_expires_at'] ?? null);

        if ($refreshExpiresAt && $refreshExpiresAt->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, bool>
     */
    public function connectionStatus(): array
    {
        $status = [];

        foreach ($this->resolver()->all() as $qbCompanyId) {
            $status[$qbCompanyId] = $this->isConnected($qbCompanyId);
        }

        return $status;
    }

    /**
     * @return array<string, QuickBooksClient>
     */
    public function allCompanies(): array
    {
        $clients = [];

        foreach ($this->resolver()->all() as $qbCompanyId) {
            try {
                $clients[$qbCompanyId] = $this->company($qbCompanyId);
            } catch (\Throwable $e) {
                $this->logger->warning('QuickBooks Manager: skipping company during allCompanies().', [
                    'qb_company_id' => $qbCompanyId,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        return $clients;
    }

    protected function resolver(): CompanyResolverInterface
    {
        if (!$this->resolver) {
            $this->resolver = $this->resolverFactory->make();
        }

        return $this->resolver;
    }

    protected function resolveCompanyRecord(string $qbCompanyId): QuickBooksCompany
    {
        $companyClass = $this->config->get('quickbooks.bridge_model');
        $tenantId     = $this->tenantContext->getTenantId();

        if (method_exists($companyClass, 'findByQbCompanyId')) {
            $record = $companyClass::findByQbCompanyId($qbCompanyId, $tenantId);
        } else {
            $record = (new $companyClass())
                ->newQuery()
                ->where('qb_company_id', $qbCompanyId)
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->first();
        }

        if (!$record) {
            throw new CompanyNotFoundException("Company record not found for [{$qbCompanyId}].");
        }

        return $record;
    }

    protected function ensureCompanyExists(string $qbCompanyId): void
    {
        if (!$this->resolver()->has($qbCompanyId)) {
            throw new CompanyNotFoundException("Unknown QuickBooks company ID [{$qbCompanyId}].");
        }
    }
}
