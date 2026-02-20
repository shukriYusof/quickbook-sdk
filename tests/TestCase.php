<?php

namespace QuickBooks\SDK\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use QuickBooks\SDK\Laravel\QuickBooksServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [QuickBooksServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('quickbooks.client_id', 'test-client-id');
        $app['config']->set('quickbooks.client_secret', 'test-client-secret-32chars-min!!');
        $app['config']->set('quickbooks.redirect_uri', 'https://test.example.com/callback');
        $app['config']->set('quickbooks.environment', 'sandbox');
        $app['config']->set('quickbooks.timeout', 30);
        $app['config']->set('quickbooks.retry_times', 3);
        $app['config']->set('quickbooks.retry_sleep', 1000);
        $app['config']->set('quickbooks.token_store', 'database');
        $app['config']->set('quickbooks.cache_store', 'array');
        $app['config']->set('quickbooks.cache_prefix', 'quickbooks_tokens');
        $app['config']->set('quickbooks.oauth.authorize_url', 'https://appcenter.intuit.com/connect/oauth2');
        $app['config']->set('quickbooks.oauth.token_url', 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer');
        $app['config']->set('quickbooks.oauth.revoke_url', 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke');
        $app['config']->set('quickbooks.oauth.scopes', ['com.intuit.quickbooks.accounting']);
        $app['config']->set('quickbooks.api_base', [
            'production' => 'https://quickbooks.api.intuit.com/v3/company/{realmId}/',
            'sandbox'    => 'https://sandbox-quickbooks.api.intuit.com/v3/company/{realmId}/',
        ]);
    }
}
