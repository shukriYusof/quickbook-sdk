# Architecture Document
## QuickBooks Online Composer SDK
**Version:** 1.0.0
**Last Updated:** 2026-02-20

---

## 1. System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                      Consumer Application                        │
│   Controller → QuickBooks Facade → QuickBooksManager            │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│                       QuickBooksManager                          │
│  ┌─────────────────┐   ┌──────────────────┐   ┌─────────────┐  │
│  │ CompanyResolver  │   │   TokenStore      │   │ OAuth2      │  │
│  │ ─────────────── │   │ ───────────────── │   │ Handler     │  │
│  │ EnvResolver      │   │ DatabaseTokenStore│   │             │  │
│  │ ModelResolver    │   │ CacheTokenStore   │   │ getAuthUrl  │  │
│  │ StaticResolver   │   │ TenantDBStore     │   │ exchange    │  │
│  │ ChainResolver    │   │                   │   │ refresh     │  │
│  └─────────────────┘   └──────────────────┘   │ revoke      │  │
│                                                 └─────────────┘  │
│  ┌───────────────────────────────────────────────────────────┐   │
│  │                   QuickBooksClient                         │   │
│  │   (one instance per company, resolved on demand)          │   │
│  │   invoices() / customers() / payments() / accounts()      │   │
│  └───────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│                      QuickBooks Online API                       │
│   oauth.platform.intuit.com                                      │
│   quickbooks.api.intuit.com/v3/company/{realmId}/               │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Database Design

```
Source Tables                Bridge Table                 Token Table
─────────────                ─────────────                ───────────

ix_companies
  id PK ──────────────┐
  company_name         │
  ...                  │
                       │      quickbooks_companies
companies              │      ─────────────────────
  id PK ──────────────┼────► id
  name                 │      qb_company_id (UUID) ◄─────── quickbooks_tokens
  ...                  │      tenant_id                       ──────────────────
                       │      source_type                     qb_company_id FK
tenants                │      source_id                       access_token
  id PK ──────────────┘      display_name                    refresh_token
  ...                         qb_realm_id                     expires_at
                               environment                     ...
[any model]                    is_active
  id PK ──────────────────►   connected_at
                               disconnected_at
```

---

## 3. Component Responsibilities

### QuickBooksManager
- Central facade entry point
- Manages per-company client cache (in-memory, per request)
- Delegates company resolution to CompanyResolver
- Delegates token storage to TokenStore
- Coordinates OAuth2 flow steps
- Provides multi-company and single-company shorthand methods

### QuickBooksClient
- Represents a single authenticated QBO company connection
- Owns token freshness check and auto-refresh
- Sends HTTP requests via Guzzle with auto-injected Bearer token
- Exposes resource accessors (invoices, customers, etc.)
- Constructed lazily — only when first accessed

### OAuth2Handler
- Stateless service — no stored state
- Generates authorization URLs with CSRF-safe state
- Exchanges authorization codes for tokens
- Refreshes access tokens using refresh tokens
- Revokes tokens at Intuit

### CompanyResolver (interface)
- Defines the set of known/valid company IDs
- Does NOT manage tokens — concerns are separated
- Drivers: env, model, static, chain
- Chain driver merges N resolvers with deduplication

### TokenStore (interface)
- Persists and retrieves token data keyed by qb_company_id
- Drivers: database, cache
- Tenant-scoped variant (TenantDatabaseTokenStore) adds tenant_id filter

### BaseResource
- Wraps QuickBooksClient HTTP methods
- Provides standard CRUD + SQL-like query abstraction
- Each resource subclass adds entity-specific methods

### QuickBooksCompany (Eloquent Model)
- Bridge between any source table and the SDK
- Polymorphic: source_type + source_id → any Eloquent model
- Generates UUID on creation
- Tracks connection state (connected_at, disconnected_at)
- registerSource() is idempotent — safe to call multiple times

---

## 4. OAuth2 Sequence

```
User          Controller        SDK Manager        Intuit
 │                │                  │                │
 │ Click Connect  │                  │                │
 │───────────────►│                  │                │
 │                │ getAuthorizationUrl(qb_company_id)│
 │                │─────────────────►│                │
 │                │   authUrl        │                │
 │                │◄─────────────────│                │
 │◄───────────────│ redirect(authUrl)│                │
 │                                   │                │
 │                Authorize at Intuit│                │
 │───────────────────────────────────────────────────►│
 │                                   │                │
 │         callback?code=X&realmId=Y&state=Z          │
 │───────────────►│                  │                │
 │                │ handleCallback(code, realmId, state)
 │                │─────────────────►│                │
 │                │                  │ exchangeCode() │
 │                │                  │───────────────►│
 │                │                  │  access_token  │
 │                │                  │  refresh_token │
 │                │                  │◄───────────────│
 │                │                  │ tokenStore.put()
 │                │  QuickBooksClient│                │
 │                │◄─────────────────│                │
```

---

## 5. Token Refresh Sequence

```
App               QuickBooksClient        TokenStore       Intuit
 │                      │                     │               │
 │ company(id).invoices()│                     │               │
 │─────────────────────►│                     │               │
 │                      │ ensureFreshToken()  │               │
 │                      │ isAccessTokenExpired? YES           │
 │                      │ isRefreshTokenExpired? NO           │
 │                      │                     │               │
 │                      │ oauth.refreshToken()│               │
 │                      │────────────────────────────────────►│
 │                      │       new access_token              │
 │                      │◄────────────────────────────────────│
 │                      │ tokenStore.put()    │               │
 │                      │────────────────────►│               │
 │                      │ proceed with API call               │
 │                      │────────────────────────────────────►│
 │                      │       response data                 │
 │◄─────────────────────│◄────────────────────────────────────│
```

---

## 6. Multi-Tenant Data Isolation

```
Request (Tenant A)                    Request (Tenant B)
──────────────────                    ──────────────────
tenant_id = "tenant-a"               tenant_id = "tenant-b"
      │                                      │
      ▼                                      ▼
SetQuickBooksTenantContext            SetQuickBooksTenantContext
middleware                            middleware
      │                                      │
      ▼                                      ▼
TenantDatabaseTokenStore             TenantDatabaseTokenStore
WHERE tenant_id = "tenant-a"         WHERE tenant_id = "tenant-b"
      │                                      │
      ▼                                      ▼
Only Tenant A tokens returned        Only Tenant B tokens returned
      │                                      │
      ▼                                      ▼
QuickBooksClient (company A1)        QuickBooksClient (company B1)
```

---

## 7. Resolver Chain Merge Logic

```
EnvResolver                ModelResolver
  ├── acme-corp               ├── 1 (Acme Sdn Bhd)
  ├── test-company            ├── 2 (Beta Holdings)
  └── beta-holdings           └── 3 (Gamma Corp)
         │                           │
         └──────────┬────────────────┘
                    ▼
              ChainResolver
              (deduplication)
                    │
                    ▼
         [acme-corp, test-company, beta-holdings, 1, 2, 3]
```

---

## 8. Directory Reference

```
src/
├── QuickBooksManager.php          Central manager, facade target
├── QuickBooksClient.php           Per-company HTTP client
├── OAuth/
│   └── OAuth2Handler.php          Stateless OAuth2 operations
├── Contracts/
│   └── TokenStoreInterface.php    Token storage contract
├── TokenStores/
│   ├── DatabaseTokenStore.php     MySQL/Postgres token persistence
│   ├── CacheTokenStore.php        Redis/cache token persistence
│   └── TenantDatabaseTokenStore.php  Tenant-scoped DB store
├── Resolvers/
│   ├── Contracts/
│   │   └── CompanyResolverInterface.php
│   ├── EnvResolver.php            Reads QUICKBOOKS_COMPANIES
│   ├── ModelResolver.php          Reads quickbooks_companies table
│   ├── StaticResolver.php         Hardcoded array
│   ├── ChainResolver.php          Merges multiple resolvers
│   └── ResolverFactory.php        Builds resolver from config
├── Resources/
│   ├── BaseResource.php           CRUD + query base class
│   ├── Invoice.php                Invoice operations
│   ├── Customer.php               Customer operations
│   ├── Payment.php                Payment operations
│   ├── Account.php                Account operations
│   ├── Vendor.php                 Vendor operations
│   ├── Bill.php                   Bill / accounts payable
│   ├── PurchaseOrder.php          Purchase order operations
│   ├── Estimate.php               Estimate / quote operations
│   ├── CreditMemo.php             Credit memo operations
│   └── Employee.php               Employee operations
├── Concerns/
│   └── ParsesDate.php             Shared Carbon date parsing trait
├── Exceptions/
│   ├── QuickBooksException.php    Base exception
│   ├── AuthenticationException.php Token/OAuth errors, invalid state signature
│   ├── CompanyNotFoundException.php Unknown company ID
│   ├── ApiException.php           Non-auth HTTP errors (404, 500, etc.)
│   └── RateLimitException.php     HTTP 429 rate-limit errors
├── Console/
│   └── RegisterCompaniesCommand.php  php artisan qb:register
└── Laravel/
    ├── QuickBooksServiceProvider.php  Auto-discovered provider
    └── Facades/
        └── QuickBooks.php             Static facade

app/ (Consumer Application)
├── Models/
│   ├── QuickBooksCompany.php      Bridge model
│   └── QuickBooksToken.php        Token model
└── Http/
    ├── Controllers/
    │   └── QuickBooksController.php
    └── Middleware/
        └── SetQuickBooksTenantContext.php

database/migrations/
├── xxxx_create_quickbooks_companies_table.php
└── xxxx_create_quickbooks_tokens_table.php
```
