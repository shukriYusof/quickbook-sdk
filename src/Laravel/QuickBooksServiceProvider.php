<?php

namespace QuickBooks\SDK\Laravel;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use QuickBooks\SDK\Console\RegisterCompaniesCommand;
use QuickBooks\SDK\Contracts\TokenStoreInterface;
use QuickBooks\SDK\OAuth\OAuth2Handler;
use QuickBooks\SDK\QuickBooksManager;
use QuickBooks\SDK\Resolvers\ResolverFactory;
use QuickBooks\SDK\Tenant\TenantContext;
use QuickBooks\SDK\TokenStores\CacheTokenStore;
use QuickBooks\SDK\TokenStores\DatabaseTokenStore;
use QuickBooks\SDK\TokenStores\TenantDatabaseTokenStore;

class QuickBooksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/quickbooks.php', 'quickbooks');

        $this->app->singleton(TenantContext::class, fn () => new TenantContext());
        $this->app->singleton(ResolverFactory::class, fn ($app) => new ResolverFactory($app));
        $this->app->singleton(OAuth2Handler::class, function ($app) {
            return new OAuth2Handler(
                $app->make(LoggerInterface::class)
            );
        });

        $this->app->singleton(TokenStoreInterface::class, function ($app) {
            $driver = config('quickbooks.token_store', 'database');

            return match ($driver) {
                'cache' => new CacheTokenStore($app['cache']),
                'tenant_database' => new TenantDatabaseTokenStore($app->make(TenantContext::class)),
                'database' => new DatabaseTokenStore(),
                default => throw new \InvalidArgumentException("Unknown token store driver [{$driver}]."),
            };
        });

        $this->app->singleton(QuickBooksManager::class, function ($app) {
            return new QuickBooksManager(
                $app['config'],
                $app->make(ResolverFactory::class),
                $app->make(TokenStoreInterface::class),
                $app->make(OAuth2Handler::class),
                $app->make(TenantContext::class),
                $app->make(LoggerInterface::class)
            );
        });

        $this->app->alias(QuickBooksManager::class, 'quickbooks');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/quickbooks.php' => config_path('quickbooks.php'),
        ], 'quickbooks-config');

        $this->publishMigrations();
        $this->publishStubs();

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterCompaniesCommand::class,
            ]);
        }
    }

    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../../stubs/migrations/create_quickbooks_companies_table.php.stub' => $this->migrationPath('create_quickbooks_companies_table.php'),
            __DIR__ . '/../../stubs/migrations/create_quickbooks_tokens_table.php.stub' => $this->migrationPath('create_quickbooks_tokens_table.php'),
        ], 'quickbooks-migrations');
    }

    protected function publishStubs(): void
    {
        $this->publishes([
            __DIR__ . '/../../stubs/models/QuickBooksCompany.php.stub' => app_path('Models/QuickBooksCompany.php'),
            __DIR__ . '/../../stubs/models/QuickBooksToken.php.stub' => app_path('Models/QuickBooksToken.php'),
            __DIR__ . '/../../stubs/middleware/SetQuickBooksTenantContext.php.stub' => app_path('Http/Middleware/SetQuickBooksTenantContext.php'),
            __DIR__ . '/../../stubs/controllers/QuickBooksController.php.stub' => app_path('Http/Controllers/QuickBooksController.php'),
        ], 'quickbooks-stubs');
    }

    protected function migrationPath(string $filename): string
    {
        return database_path('migrations/' . date('Y_m_d_His') . '_' . $filename);
    }
}
