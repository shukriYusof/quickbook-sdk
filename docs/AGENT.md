# AI Agent Reference (AGENT.md)
## QuickBooks Online Composer SDK
**Version:** 1.0.0
**Purpose:** This file is optimised for AI agents, code copilots, and LLM assistants.
**Last Updated:** 2026-02-20

---

## What This SDK Does

Laravel Composer SDK for QuickBooks Online (QBO) that supports:
- Single company or N companies per app
- Multi-tenant (shared DB or per-tenant DB)
- Any source table (ix_companies, companies, tenants, etc.)
- Auto-refreshing OAuth2 tokens per company
- Driver-based company resolution and token storage

---

## Key Identifiers

| Identifier | Source | Usage |
|------------|--------|-------|
| `qb_company_id` | UUID, SDK-generated | Primary key across SDK — always use this |
| `realm_id` | Assigned by QBO on OAuth | Appended to QBO API base URL |
| `company_id` (legacy) | Your internal ID | Used in early SDK design — now replaced by `qb_company_id` |
| `tenant_id` | Your app | Scopes token and company queries in multi-tenant setup |
| `source_type` | PHP class name | Polymorphic reference to source model |
| `source_id` | PK of source model | Polymorphic reference to source record |

---

## Core Classes

```
QuickBooksManager          → app('quickbooks') or QuickBooks facade
QuickBooksClient           → returned by QuickBooks::company($uuid)
OAuth2Handler              → internal, injected into manager
DatabaseTokenStore         → default token store driver
CacheTokenStore            → alternative token store driver
TenantDatabaseTokenStore   → multi-tenant variant of database store
EnvResolver                → reads QUICKBOOKS_COMPANIES from .env
ModelResolver              → reads from quickbooks_companies table
StaticResolver             → hardcoded array (testing)
ChainResolver              → merges multiple resolvers
ResolverFactory            → builds resolver from config driver string
QuickBooksCompany          → Eloquent bridge model (app/Models)
QuickBooksToken            → Eloquent token model (app/Models)
```

---

## Common Tasks → Code

### Register a source model (one-time setup)
```php
$qbComp = QuickBooksCompany::registerSource($anyEloquentModel, $tenantId, 'name');
// Returns QuickBooksCompany with $qb_company_id UUID
```

### Get authorization URL
```php
$url = QuickBooks::getAuthorizationUrl($qbComp->qb_company_id);
// Embed UUID in OAuth state — decoded automatically in callback
```

### Handle OAuth callback
```php
$client = QuickBooks::handleCallback($code, $realmId, $state);
// Stores tokens, returns QuickBooksClient
```

### Get API client for a company
```php
$client = QuickBooks::company($qbComp->qb_company_id);
```

### Get default company client (single-company apps)
```php
$client = QuickBooks::client();
// Requires QUICKBOOKS_DEFAULT_COMPANY in .env
```

### Check connection status
```php
QuickBooks::isConnected($qbCompanyId);  // bool
QuickBooks::connectionStatus();          // [uuid => bool, ...]
```

### Fetch data
```php
// Invoices
$client->invoices()->all();
$client->invoices()->find($id);
$client->invoices()->overdue();

// Customers
$client->customers()->active();
$client->customers()->findByEmail($email);

// Accounts
$client->accounts()->getByType('Expense');

// Vendors
$client->vendors()->active();
$client->vendors()->findByName('Acme Corp');

// Bills
$client->bills()->unpaid();
$client->bills()->forVendor($vendorId);

// Purchase Orders
$client->purchaseOrders()->open();
$client->purchaseOrders()->forVendor($vendorId);

// Estimates
$client->estimates()->pending();
$client->estimates()->accepted();
$client->estimates()->forCustomer($customerId);

// Credit Memos
$client->creditMemos()->unapplied();
$client->creditMemos()->forCustomer($customerId);

// Employees
$client->employees()->active();
$client->employees()->findByName('John Doe');

// Raw SQL-like query (any entity)
$client->query("SELECT * FROM Invoice WHERE Balance > '0'");
```

### Calling resources not covered by a typed class
```php
// Any QBO entity via raw HTTP methods
$client->get('taxrate/1');
$client->get('taxcode');
$client->post('journalentry', [...]);
$client->query("SELECT * FROM JournalEntry WHERE DocNumber = 'JE-001'");
$client->query("SELECT * FROM TaxRate WHERE Active = true");
$client->query("SELECT * FROM CompanyInfo");
```

### Create / Update
```php
$client->invoices()->create([...]);
$client->invoices()->update(['Id' => '1', 'SyncToken' => '0', ...]);
$client->invoices()->sparseUpdate(['Id' => '1', 'SyncToken' => '0', 'PrivateNote' => 'x']);
```

### Disconnect
```php
QuickBooks::disconnectCompany($qbCompanyId);
```

### Find bridge record from source model
```php
$qbComp = QuickBooksCompany::findBySource($sourceModel, $tenantId);
```

### Bulk operation across all connected companies
```php
foreach (QuickBooks::allCompanies() as $client) {
    $client->invoices()->overdue();
}
```

---

## .env Keys (All)

```dotenv
QUICKBOOKS_CLIENT_ID=
QUICKBOOKS_CLIENT_SECRET=
QUICKBOOKS_REDIRECT_URI=https://app.com/quickbooks/callback
QUICKBOOKS_ENVIRONMENT=production        # or sandbox
QUICKBOOKS_TOKEN_STORE=database          # or cache
QUICKBOOKS_DEFAULT_COMPANY=             # UUID or slug for single-company apps
QUICKBOOKS_COMPANIES=uuid-1,uuid-2      # comma-separated (env resolver)
QUICKBOOKS_COMPANY_RESOLVER=model       # env | model | static | chain
QUICKBOOKS_COMPANY_MODEL=App\Models\Company
QUICKBOOKS_COMPANY_ID_COLUMN=id
QUICKBOOKS_COMPANY_LABEL_COLUMN=name
QUICKBOOKS_CACHE_STORE=redis
QUICKBOOKS_TIMEOUT=30                   # Guzzle HTTP timeout in seconds (default: 30)
QUICKBOOKS_RETRY_TIMES=3                # Max retry attempts on 429/5xx (default: 3)
QUICKBOOKS_RETRY_SLEEP=1000             # Base retry delay in ms, doubles per attempt (default: 1000)
```

---

## Config Keys (config/quickbooks.php)

```php
client_id, client_secret, redirect_uri, environment
token_store              // database | cache
token_table              // quickbooks_tokens
cache_store, cache_prefix
default_company
timeout                  // Guzzle HTTP timeout in seconds (default: 30)
retry_times              // max retry attempts for 429/5xx responses (default: 3)
retry_sleep              // base delay in ms between retries, doubles each attempt (default: 1000)
company_resolver         // env | model | static | chain
companies                // array parsed from QUICKBOOKS_COMPANIES
company_model.model      // Eloquent class
company_model.id_column
company_model.label_column
company_model.conditions // where filters e.g. ['is_active' => true]
chain_resolvers          // ['env', 'model']
```

---

## Exceptions

```php
QuickBooksException          // base — catch-all
AuthenticationException      // OAuth2 errors, expired/missing token, invalid state signature (extends QuickBooksException)
CompanyNotFoundException     // unknown qb_company_id or no tokens found (extends QuickBooksException)
ApiException                 // non-auth HTTP errors: 404, 500, etc. (extends QuickBooksException)
RateLimitException           // HTTP 429 rate-limit from QBO (extends ApiException)
```

---

## Tables

```sql
-- Bridge
quickbooks_companies (id, qb_company_id UUID, tenant_id, source_type,
                      source_id, display_name, qb_realm_id, environment,
                      is_active, connected_at, disconnected_at, timestamps)

-- Tokens
quickbooks_tokens (id, qb_company_id UUID FK, access_token, refresh_token,
                   access_token_expires_at, refresh_token_expires_at, timestamps)
```

---

## Token Lifecycle

```
access_token  → expires in 3,600 seconds (1 hour)
                auto-refreshed by ensureFreshToken() before every request

refresh_token → expires in ~8,640,000 seconds (~100 days)
                if expired → AuthenticationException → user must re-authorize
```

---

## Supported Configurations

| Config | How to enable |
|--------|--------------|
| Single company | Set QUICKBOOKS_DEFAULT_COMPANY; call QuickBooks::client() |
| Multi-company (.env) | QUICKBOOKS_COMPANY_RESOLVER=env + QUICKBOOKS_COMPANIES |
| Multi-company (DB) | QUICKBOOKS_COMPANY_RESOLVER=model |
| Multi-company (both) | QUICKBOOKS_COMPANY_RESOLVER=chain |
| Multi-tenant shared DB | Use TenantDatabaseTokenStore + middleware |
| Multi-tenant per-DB | stancl/tenancy — works automatically |
| ix_companies source | registerSource($ixCompany, null, 'company_name') |
| companies source | registerSource($company, null, 'name') |
| Custom source | registerSource($anyEloquentModel, $tenantId, 'label_col') |

---

## Artisan Commands

```bash
php artisan qb:register "App\Models\IxCompany" --label=company_name
php artisan qb:register "App\Models\Company" --label=name
php artisan qb:register "App\Models\Company" --label=name --tenant=tenant-1
php artisan qb:register "App\Models\Company" --label=name --filter="is_active=1"
```

---

## File Map (for code generation)

| File | Namespace | Purpose |
|------|-----------|---------|
| `src/QuickBooksManager.php` | `YourVendor\QuickBooks` | Main hub |
| `src/QuickBooksClient.php` | `YourVendor\QuickBooks` | Per-company client |
| `src/OAuth/OAuth2Handler.php` | `YourVendor\QuickBooks\OAuth` | OAuth2 (HMAC-signed state, retry) |
| `src/Contracts/TokenStoreInterface.php` | `YourVendor\QuickBooks\Contracts` | Contract |
| `src/TokenStores/DatabaseTokenStore.php` | `YourVendor\QuickBooks\TokenStores` | DB store |
| `src/TokenStores/CacheTokenStore.php` | `YourVendor\QuickBooks\TokenStores` | Cache store (TTL-aware) |
| `src/TokenStores/TenantDatabaseTokenStore.php` | `YourVendor\QuickBooks\TokenStores` | Tenant DB store |
| `src/Resolvers/EnvResolver.php` | `YourVendor\QuickBooks\Resolvers` | .env resolver |
| `src/Resolvers/ModelResolver.php` | `YourVendor\QuickBooks\Resolvers` | DB resolver |
| `src/Resolvers/ChainResolver.php` | `YourVendor\QuickBooks\Resolvers` | Chain resolver |
| `src/Resolvers/ResolverFactory.php` | `YourVendor\QuickBooks\Resolvers` | Factory |
| `src/Resources/BaseResource.php` | `YourVendor\QuickBooks\Resources` | Base CRUD |
| `src/Resources/Invoice.php` | `YourVendor\QuickBooks\Resources` | Invoice |
| `src/Resources/Customer.php` | `YourVendor\QuickBooks\Resources` | Customer (validated email query) |
| `src/Resources/Payment.php` | `YourVendor\QuickBooks\Resources` | Payment |
| `src/Resources/Account.php` | `YourVendor\QuickBooks\Resources` | Account (whitelisted type query) |
| `src/Resources/Vendor.php` | `YourVendor\QuickBooks\Resources` | Vendor |
| `src/Resources/Bill.php` | `YourVendor\QuickBooks\Resources` | Bill (accounts payable) |
| `src/Resources/PurchaseOrder.php` | `YourVendor\QuickBooks\Resources` | Purchase Order |
| `src/Resources/Estimate.php` | `YourVendor\QuickBooks\Resources` | Estimate / Quote |
| `src/Resources/CreditMemo.php` | `YourVendor\QuickBooks\Resources` | Credit Memo |
| `src/Resources/Employee.php` | `YourVendor\QuickBooks\Resources` | Employee |
| `src/Exceptions/QuickBooksException.php` | `YourVendor\QuickBooks\Exceptions` | Base exception |
| `src/Exceptions/AuthenticationException.php` | `YourVendor\QuickBooks\Exceptions` | Auth/token errors |
| `src/Exceptions/CompanyNotFoundException.php` | `YourVendor\QuickBooks\Exceptions` | Unknown company |
| `src/Exceptions/ApiException.php` | `YourVendor\QuickBooks\Exceptions` | Non-auth HTTP errors |
| `src/Exceptions/RateLimitException.php` | `YourVendor\QuickBooks\Exceptions` | HTTP 429 rate limit |
| `src/Concerns/ParsesDate.php` | `YourVendor\QuickBooks\Concerns` | Shared Carbon date parsing trait |
| `src/Laravel/QuickBooksServiceProvider.php` | `YourVendor\QuickBooks\Laravel` | Provider |
| `src/Laravel/Facades/QuickBooks.php` | `YourVendor\QuickBooks\Laravel\Facades` | Facade |
| `app/Models/QuickBooksCompany.php` | `App\Models` | Bridge model |
| `app/Models/QuickBooksToken.php` | `App\Models` | Token model |
| `app/Http/Middleware/SetQuickBooksTenantContext.php` | `App\Http\Middleware` | Tenant middleware |
