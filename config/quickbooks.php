<?php

use QuickBooks\SDK\Models\QuickBooksCompany;
use QuickBooks\SDK\Models\QuickBooksToken;

return [
    'client_id' => env('QUICKBOOKS_CLIENT_ID'),
    'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
    'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
    'environment' => env('QUICKBOOKS_ENVIRONMENT', 'production'),

    'token_store' => env('QUICKBOOKS_TOKEN_STORE', 'database'),
    'token_table' => 'quickbooks_tokens',
    'company_table' => 'quickbooks_companies',

    'cache_store' => env('QUICKBOOKS_CACHE_STORE', config('cache.default')),
    'cache_prefix' => env('QUICKBOOKS_CACHE_PREFIX', 'quickbooks_tokens'),

    'default_company' => env('QUICKBOOKS_DEFAULT_COMPANY'),

    // HTTP client settings.
    'timeout' => env('QUICKBOOKS_TIMEOUT', 30),

    // Retry settings for failed API requests (exponential backoff).
    // retry_times: maximum number of retry attempts (not counting the first request).
    // retry_sleep: base delay in milliseconds between retries (doubles each attempt).
    'retry_times' => env('QUICKBOOKS_RETRY_TIMES', 3),
    'retry_sleep' => env('QUICKBOOKS_RETRY_SLEEP', 1000),

    'company_resolver' => env('QUICKBOOKS_COMPANY_RESOLVER', 'model'),
    'companies' => array_values(array_filter(array_map('trim', explode(',', env('QUICKBOOKS_COMPANIES', ''))))),

    // Bridge + token models used by the SDK internally.
    'bridge_model' => QuickBooksCompany::class,
    'token_model' => QuickBooksToken::class,

    // Optional source-model config for registration helpers/commands.
    'company_model' => [
        'model' => env('QUICKBOOKS_COMPANY_MODEL', 'App\\Models\\Company'),
        'id_column' => env('QUICKBOOKS_COMPANY_ID_COLUMN', 'id'),
        'label_column' => env('QUICKBOOKS_COMPANY_LABEL_COLUMN', 'name'),
        'conditions' => [],
    ],

    'chain_resolvers' => ['env', 'model'],

    'oauth' => [
        'authorize_url' => 'https://appcenter.intuit.com/connect/oauth2',
        'token_url' => 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer',
        'revoke_url' => 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke',
        'scopes' => ['com.intuit.quickbooks.accounting'],
    ],

    'api_base' => [
        'production' => 'https://quickbooks.api.intuit.com/v3/company/{realmId}/',
        'sandbox' => 'https://sandbox-quickbooks.api.intuit.com/v3/company/{realmId}/',
    ],
];
