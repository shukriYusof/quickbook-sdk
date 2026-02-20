# QuickBooks Online SDK for Laravel

A Laravel-first Composer SDK for QuickBooks Online (QBO). Supports single-company apps, multi-company setups, and full SaaS multi-tenant platforms from the same codebase — with driver-based token storage, flexible company resolution, and automatic OAuth2 token refresh.

---

## Requirements

| | Version |
|---|---|
| PHP | 8.1+ |
| Laravel | 10.x / 11.x / 12.x |

---

## Installation

```bash
composer require shukyusof/qb-sdk
```

Laravel auto-discovers the service provider. To publish the config and migrations:

```bash
php artisan vendor:publish --tag=quickbooks-config
php artisan vendor:publish --tag=quickbooks-migrations
php artisan vendor:publish --tag=quickbooks-stubs
php artisan migrate
```

---

## Configuration

Add these to your `.env`:

```dotenv
QUICKBOOKS_CLIENT_ID=your_client_id
QUICKBOOKS_CLIENT_SECRET=your_client_secret
QUICKBOOKS_REDIRECT_URI=https://yourapp.com/quickbooks/callback
QUICKBOOKS_ENVIRONMENT=production          # or sandbox
QUICKBOOKS_TOKEN_STORE=database            # or cache
QUICKBOOKS_COMPANY_RESOLVER=model          # env | model | static | chain

# Single-company shorthand
QUICKBOOKS_DEFAULT_COMPANY=your-qb-company-uuid

# Optional tuning
QUICKBOOKS_TIMEOUT=30
QUICKBOOKS_RETRY_TIMES=3
QUICKBOOKS_RETRY_SLEEP=1000
```

---

## Quickstart

### 1. Register a company source (one-time)

```php
use App\Models\QuickBooksCompany;

$company = QuickBooksCompany::registerSource($yourModel, tenantId: null, labelColumn: 'name');
// $company->qb_company_id  →  UUID used everywhere in the SDK
```

### 2. Redirect the user to QuickBooks to authorize

```php
use QuickBooks\SDK\Laravel\Facades\QuickBooks;

$url = QuickBooks::getAuthorizationUrl($company->qb_company_id);
return redirect($url);
```

### 3. Handle the OAuth callback

```php
public function callback(Request $request)
{
    $client = QuickBooks::handleCallback(
        $request->get('code'),
        $request->get('realmId'),
        $request->get('state')
    );

    // $client is ready to use immediately
}
```

### 4. Use the client

```php
$client = QuickBooks::company($qbCompanyId);

// or for single-company apps:
$client = QuickBooks::client();
```

---

## Available Resources

All resources inherit `all()`, `find($id)`, `create($payload)`, `update($payload)`, `sparseUpdate($payload)`, and `query($sql)` from `BaseResource`.

### Invoices

```php
$client->invoices()->all();
$client->invoices()->find('123');
$client->invoices()->overdue();             // Balance > 0
$client->invoices()->create([...]);
$client->invoices()->sparseUpdate(['Id' => '1', 'SyncToken' => '0', 'PrivateNote' => 'x']);
```

### Customers

```php
$client->customers()->all();
$client->customers()->active();
$client->customers()->findByEmail('user@example.com');
$client->customers()->create([...]);
```

### Payments

```php
$client->payments()->all();
$client->payments()->find('456');
$client->payments()->create([...]);
```

### Accounts (Chart of Accounts)

```php
$client->accounts()->all();
$client->accounts()->getByType('Expense');
// Valid types: Bank, Other Current Asset, Fixed Asset, Other Asset,
//   Accounts Receivable, Equity, Expense, Other Expense,
//   Cost of Goods Sold, Accounts Payable, Credit Card,
//   Long Term Liability, Other Current Liability, Income, Other Income
```

### Vendors

```php
$client->vendors()->all();
$client->vendors()->active();
$client->vendors()->findByName('Acme Supplies');
$client->vendors()->create([...]);
```

### Bills (Accounts Payable)

```php
$client->bills()->all();
$client->bills()->unpaid();                 // Balance > 0
$client->bills()->forVendor($vendorId);
$client->bills()->create([...]);
```

### Purchase Orders

```php
$client->purchaseOrders()->all();
$client->purchaseOrders()->open();          // POStatus = 'Open'
$client->purchaseOrders()->forVendor($vendorId);
$client->purchaseOrders()->create([...]);
```

### Estimates

```php
$client->estimates()->all();
$client->estimates()->pending();            // TxnStatus = 'Pending'
$client->estimates()->accepted();           // TxnStatus = 'Accepted'
$client->estimates()->forCustomer($customerId);
$client->estimates()->create([...]);
```

### Credit Memos

```php
$client->creditMemos()->all();
$client->creditMemos()->unapplied();        // Balance > 0
$client->creditMemos()->forCustomer($customerId);
$client->creditMemos()->create([...]);
```

### Employees

```php
$client->employees()->all();
$client->employees()->active();
$client->employees()->findByName('Jane Smith');
$client->employees()->create([...]);
```

---

## Calling Resources Not Covered by a Typed Class

The SDK ships typed classes for the most commonly used QBO entities. For everything else — `JournalEntry`, `TaxRate`, `TaxCode`, `CompanyInfo`, `Department`, `Class`, `Item`, `RefundReceipt`, `SalesReceipt`, `Transfer`, `Deposit`, and any other QBO entity — use the raw client methods directly.

### `get($uri, $query = [])`

```php
// Fetch a single record
$client->get('taxrate/1');
$client->get('item/42');
$client->get('department/5');

// Fetch with query parameters
$client->get('companyinfo/' . $realmId);
```

### `post($uri, $payload = [])`

```php
// Create any entity
$client->post('journalentry', [
    'Line' => [...],
    'CurrencyRef' => ['value' => 'USD'],
]);

// Create a sales receipt
$client->post('salesreceipt', [
    'CustomerRef' => ['value' => '1'],
    'Line' => [...],
]);
```

### `query($sql)`

QBO supports a SQL-like query language across all entities. Use this for any entity that supports querying:

```php
// Journal entries
$client->query("SELECT * FROM JournalEntry WHERE DocNumber = 'JE-001'");

// Tax rates
$client->query("SELECT * FROM TaxRate WHERE Active = true");

// Items / products
$client->query("SELECT * FROM Item WHERE Type = 'Inventory'");
$client->query("SELECT * FROM Item WHERE Name LIKE '%Widget%'");

// Company info
$client->query("SELECT * FROM CompanyInfo");

// Departments
$client->query("SELECT * FROM Department WHERE Active = true");

// Classes
$client->query("SELECT * FROM Class WHERE Active = true");

// Deposits
$client->query("SELECT * FROM Deposit WHERE TotalAmt > '1000'");

// Sales receipts
$client->query("SELECT * FROM SalesReceipt WHERE Balance > '0'");

// Refund receipts
$client->query("SELECT * FROM RefundReceipt");

// Transfers
$client->query("SELECT * FROM Transfer");

// Time activities
$client->query("SELECT * FROM TimeActivity WHERE BillableStatus = 'Billable'");
```

> **Note:** String values in QBO query language must be wrapped in single quotes. Numeric comparisons do not require quotes.

---

## Exception Handling

```php
use QuickBooks\SDK\Exceptions\AuthenticationException;
use QuickBooks\SDK\Exceptions\RateLimitException;
use QuickBooks\SDK\Exceptions\ApiException;
use QuickBooks\SDK\Exceptions\CompanyNotFoundException;
use QuickBooks\SDK\Exceptions\QuickBooksException;

try {
    $invoices = QuickBooks::company($id)->invoices()->all();
} catch (RateLimitException $e) {
    // HTTP 429 — back off and retry later
} catch (AuthenticationException $e) {
    // OAuth expired or invalid — re-authorize the company
} catch (CompanyNotFoundException $e) {
    // Unknown qb_company_id
} catch (ApiException $e) {
    // Other HTTP error (404, 500, etc.)
    // $e->getCode() returns the HTTP status code
} catch (QuickBooksException $e) {
    // Catch-all for any SDK exception
}
```

---

## Multi-Company

```php
// Check connection state
QuickBooks::isConnected($qbCompanyId);   // bool
QuickBooks::connectionStatus();           // ['uuid' => bool, ...]

// Iterate all connected companies
foreach (QuickBooks::allCompanies() as $qbCompanyId => $client) {
    $client->invoices()->overdue();
}

// Disconnect
QuickBooks::disconnectCompany($qbCompanyId);
```

---

## Multi-Tenancy

Set the tenant context before resolving a client — typically in middleware:

```php
use QuickBooks\SDK\Tenant\TenantContext;

app(TenantContext::class)->setTenantId($request->user()->tenant_id);
```

Then use the SDK normally. All token reads and writes are automatically scoped to the active tenant.

---

## Token Storage Drivers

| Driver | Config | Use case |
|---|---|---|
| `database` | `QUICKBOOKS_TOKEN_STORE=database` | Default, persistent |
| `cache` | `QUICKBOOKS_TOKEN_STORE=cache` | Redis/Memcached, TTL-aware |
| `tenant_database` | `QUICKBOOKS_TOKEN_STORE=tenant_database` | Multi-tenant DB isolation |

---

## Company Resolver Drivers

| Driver | Config | Use case |
|---|---|---|
| `model` | `QUICKBOOKS_COMPANY_RESOLVER=model` | Reads from `quickbooks_companies` table |
| `env` | `QUICKBOOKS_COMPANY_RESOLVER=env` | Comma-separated UUIDs in `QUICKBOOKS_COMPANIES` |
| `static` | `QUICKBOOKS_COMPANY_RESOLVER=static` | Hardcoded array, useful for testing |
| `chain` | `QUICKBOOKS_COMPANY_RESOLVER=chain` | Merges multiple resolvers with deduplication |

---

## Artisan Commands

```bash
# Register all records from a model as QBO companies
php artisan qb:register "App\Models\Company" --label=name

# With tenant scoping
php artisan qb:register "App\Models\Company" --label=name --tenant=tenant-uuid

# With a where-filter
php artisan qb:register "App\Models\Company" --label=name --filter="is_active=1"
```

---

## License

MIT
