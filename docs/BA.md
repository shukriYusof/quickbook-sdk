# Business Analysis Document (BA)
## QuickBooks Online Composer SDK
**Version:** 1.0.0
**Status:** In Development
**Last Updated:** 2026-02-20
**Analyst:** Business Analyst

---

## 1. Business Context

QuickBooks Online is one of the most widely used cloud accounting platforms in Southeast Asia and globally. Organisations with multiple legal entities, SaaS platforms serving multiple clients, or enterprise systems managing groups of companies all need to integrate with QBO at scale — not just as a single connection.

The current state of available SDKs does not address multi-entity or multi-tenant scenarios, forcing engineering teams to reinvent the integration layer on every project. This SDK standardises that layer as a reusable Composer package.

---

## 2. Stakeholders

| Stakeholder | Role | Interest |
|-------------|------|----------|
| Engineering Lead | SDK author & maintainer | Maintainability, reusability |
| Project Manager | Delivery oversight | Timeline, scope, risk |
| Client Companies | SDK consumer | Reliable QBO data sync |
| Finance/Accounting Teams | End users of synced data | Data accuracy, real-time sync |
| IT/Ops | Deployment & monitoring | Token health, server stability |

---

## 3. Business Processes Supported

### BP-01: Company Registration
Registering a source company record into the SDK bridge layer.

```
Trigger:    New company/entity added to source table
Actor:      Developer / Admin
Steps:
  1. Source model (ix_company, company, etc.) created in source table
  2. Developer calls QuickBooksCompany::registerSource($model)
  3. Bridge table records: qb_company_id (UUID), source_type, source_id
  4. Company is now "known" to SDK — ready for OAuth connect
Outcome:    qb_company_id UUID generated and stored
```

### BP-02: OAuth2 Connect
Linking a registered company to a live QBO account.

```
Trigger:    Admin initiates connect for a company
Actor:      Admin User
Steps:
  1. Admin clicks "Connect to QuickBooks" for a company
  2. System generates QBO authorization URL with qb_company_id in state
  3. Admin redirected to QBO login/authorize page
  4. Admin authorizes the app for their QBO company
  5. QBO redirects back with code + realmId
  6. SDK exchanges code for access_token + refresh_token
  7. Tokens stored in quickbooks_tokens
  8. Bridge record updated with qb_realm_id + connected_at
Outcome:    Company is fully connected; API calls can now be made
```

### BP-03: API Data Retrieval
Fetching financial data from QBO for a connected company.

```
Trigger:    Application requests QBO data
Actor:      System (automated) or User (on-demand)
Steps:
  1. App resolves QuickBooksClient via QuickBooks::company($qbCompanyId)
  2. SDK checks access token expiry
  3a. Token valid → proceeds to API call
  3b. Token expired → auto-refresh using refresh_token → proceeds
  3c. Refresh token expired → throws AuthenticationException
  4. HTTP request sent to QBO API with Bearer token
  5. Response returned as PHP array
Outcome:    Financial data available for processing/display
```

### BP-04: Disconnect
Revoking a company's QBO connection.

```
Trigger:    Admin initiates disconnect or subscription cancelled
Actor:      Admin User / System
Steps:
  1. SDK calls Intuit revoke endpoint with refresh_token
  2. Tokens deleted from quickbooks_tokens
  3. Bridge record updated with disconnected_at
Outcome:    Company no longer connected; re-auth required to reconnect
```

---

## 4. Data Dictionary

### `quickbooks_companies` Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | BIGINT | No | Auto-increment PK |
| `qb_company_id` | UUID | No | SDK-generated stable identifier |
| `tenant_id` | VARCHAR | Yes | Tenant identifier for multi-tenant apps |
| `source_type` | VARCHAR | No | Eloquent model class (e.g. App\Models\Company) |
| `source_id` | BIGINT | No | Primary key in source table |
| `display_name` | VARCHAR | Yes | Cached company name for display |
| `qb_realm_id` | VARCHAR | Yes | QBO-assigned company identifier |
| `environment` | VARCHAR | No | `production` or `sandbox` |
| `is_active` | BOOLEAN | No | Whether company is active in SDK |
| `connected_at` | TIMESTAMP | Yes | When last successfully connected |
| `disconnected_at` | TIMESTAMP | Yes | When disconnected (null = connected) |
| `created_at` | TIMESTAMP | No | Record creation time |
| `updated_at` | TIMESTAMP | No | Last update time |

### `quickbooks_tokens` Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | BIGINT | No | Auto-increment PK |
| `qb_company_id` | UUID | No | FK → quickbooks_companies |
| `access_token` | TEXT | No | QBO Bearer token (expires 1 hour) |
| `refresh_token` | TEXT | No | QBO refresh token (expires ~100 days) |
| `access_token_expires_at` | TIMESTAMP | Yes | Access token expiry time |
| `refresh_token_expires_at` | TIMESTAMP | Yes | Refresh token expiry time |
| `created_at` | TIMESTAMP | No | Record creation time |
| `updated_at` | TIMESTAMP | No | Last update time |

---

## 5. Business Rules

| Rule ID | Rule | Enforcement |
|---------|------|-------------|
| BR-01 | One QBO realmId per qb_company_id | Unique constraint on qb_realm_id |
| BR-02 | One token record per qb_company_id | Unique constraint on tokens.qb_company_id |
| BR-03 | source_type + source_id is unique per tenant | Composite unique index |
| BR-04 | Tokens must be refreshed before expiry | ensureFreshToken() on every request |
| BR-05 | Disconnected companies must not make API calls | isConnected() check before resolution |
| BR-06 | Tenant A cannot access Tenant B tokens | tenant_id scoping at store layer |
| BR-07 | qb_company_id is immutable once created | UUID generated once on insert |
| BR-08 | source table deletion must not orphan tokens | Soft-delete source models; hard-delete via SDK disconnect |

---

## 6. Integration Points

### QBO API Endpoints Used

| Endpoint | Purpose | Called By |
|----------|---------|-----------|
| `appcenter.intuit.com/connect/oauth2` | Authorization redirect | OAuth2Handler::getAuthorizationUrl() |
| `oauth.platform.intuit.com/oauth2/v1/tokens/bearer` | Token exchange & refresh | OAuth2Handler::exchangeCode(), refreshToken() |
| `developer.api.intuit.com/v2/oauth2/tokens/revoke` | Token revocation | OAuth2Handler::revokeToken() |
| `quickbooks.api.intuit.com/v3/company/{realmId}/*` | All data operations | QuickBooksClient::request() |
| `sandbox-quickbooks.api.intuit.com/v3/company/{realmId}/*` | Sandbox data operations | QuickBooksClient::request() |

### Internal Integration Points

| System | Integration Type | Data Flow |
|--------|-----------------|-----------|
| Source tables (ix_companies, companies, etc.) | Eloquent polymorphic | Source → quickbooks_companies |
| Laravel Cache (Redis) | Optional token store | quickbooks_tokens ↔ Cache |
| Laravel DB (MySQL) | Primary token store | quickbooks_tokens ↔ MySQL |
| Laravel Queue | Future: async sync jobs | App → Queue → QBO API |

---

## 7. Use Case Matrix

| Use Case | Single Company | Multi-Company | Multi-Tenant | Source Agnostic |
|----------|:--------------:|:-------------:|:------------:|:---------------:|
| Startup SaaS (1 QBO account) | ✅ | — | — | — |
| Holding group (5–7 entities) | — | ✅ | — | ✅ |
| SaaS platform (N clients) | — | ✅ | ✅ | ✅ |
| Mixed source tables (ix + companies) | — | ✅ | — | ✅ |
| Per-tenant DB (stancl/tenancy) | — | ✅ | ✅ | ✅ |

---

## 8. Acceptance Criteria

| ID | Criteria |
|----|---------|
| AC-01 | A new company registered via `registerSource()` gets a stable UUID |
| AC-02 | OAuth connect flow completes and tokens are persisted correctly |
| AC-03 | API calls with expired access token auto-refresh without developer intervention |
| AC-04 | Expired refresh token throws `AuthenticationException` with clear message |
| AC-05 | `QuickBooks::company($uuid)` returns correct client for each registered company |
| AC-06 | Tenant A's `company()` call cannot return Tenant B's tokens |
| AC-07 | Changing source table name does not invalidate existing qb_company_id records |
| AC-08 | `qb:register` command registers all source records in one run |
| AC-09 | Disconnect revokes token at Intuit and nullifies local tokens |
| AC-10 | Chain resolver returns deduplicated union of all driver results |
