# Product Requirements Document (PRD)
## QuickBooks Online Composer SDK
**Version:** 1.0.0
**Status:** In Development
**Last Updated:** 2026-02-20
**Owner:** Engineering Lead

---

## 1. Executive Summary

A Laravel-first PHP Composer SDK that enables any application to connect to QuickBooks Online (QBO) regardless of how its internal company/tenant data is structured. The SDK abstracts QBO OAuth2, token lifecycle, and multi-company routing behind a clean, source-agnostic API — supporting single-company apps, multi-entity businesses, and full SaaS multi-tenant platforms from the same codebase.

---

## 2. Problem Statement

### Current Pain Points
- Existing QBO SDKs (Intuit official, consolibyte) assume one app = one QBO company
- Multi-company setups require manual token management and company routing
- No Laravel-native integration — no service provider, facade, or config system
- Token storage is file-based or completely DIY
- No concept of multi-tenancy — tenant isolation must be built from scratch every time
- Source table structure (ix_companies, companies, tenants, etc.) is tightly coupled to QBO connection logic
- Switching or adding a new source table requires rearchitecting the integration

### Business Impact
- High development cost per new QBO integration project
- Token expiry bugs cause silent data sync failures
- Multi-company enterprise clients cannot be served without custom engineering
- New projects cannot reuse QBO integration work from previous projects

---

## 3. Goals & Objectives

### Primary Goals
1. Provide a single installable Composer package that works for any Laravel project
2. Support 1 to N companies per app with zero architectural change
3. Support multi-tenancy (shared DB and per-tenant DB) natively
4. Decouple QBO connection logic from any specific source table structure
5. Handle all OAuth2 token lifecycle automatically (exchange, refresh, revoke)

### Success Metrics
- New project QBO integration time: < 1 hour (vs 2–3 days currently)
- Zero token expiry incidents due to auto-refresh
- Same SDK version used across all company projects
- New source table onboarding: 1 Artisan command

---

## 4. Target Users

### Primary Users
| User | Context | Need |
|------|---------|------|
| Senior PHP/Laravel Engineer | Building CRM/ERP with QBO sync | Drop-in SDK with multi-company support |
| Technical Lead | Managing 2+ projects sharing QBO | Reusable, maintainable integration layer |
| Full-Stack Developer | Single-company SaaS app | Simple connect-and-use QBO integration |

### Secondary Users
| User | Context | Need |
|------|---------|------|
| Product Manager | Scoping QBO features | Clear capability map |
| Business Analyst | Documenting QBO flows | Integration requirements reference |
| AI Agent / Copilot | Code generation | Structured SDK reference for accurate suggestions |

---

## 5. Scope

### In Scope (v1.0)
- OAuth2 authorization code flow
- Multi-company connection management
- Multi-tenant token isolation
- Polymorphic source model support (any Eloquent model)
- Database and Cache token stores
- Company resolver drivers: env, model, static, chain
- Resources: Invoice, Customer, Payment, Account
- Laravel service provider, facade, config
- Artisan command: qb:register
- Exception hierarchy

### Out of Scope (v1.0)
- Webhook / event subscription management
- QuickBooks Payroll API
- QuickBooks Time (TSheets) API
- Batch API operations
- Non-Laravel PHP frameworks
- GraphQL API support
- Automatic DB sync / bi-directional sync

### Future Roadmap (v1.x+)
- Additional resources: Vendor, Bill, PurchaseOrder, Employee
- Webhook listener and event dispatcher
- Sync job scaffolding (queued, retryable)
- Admin UI (Livewire/Inertia) for connection management
- OpenAPI spec generation
- Automated token health monitoring

---

## 6. Functional Requirements

### FR-01: OAuth2 Flow
- System MUST generate authorization URLs with company identity embedded in state
- System MUST exchange authorization codes for access + refresh tokens
- System MUST store tokens per company using a configurable storage driver
- System MUST revoke tokens at Intuit on disconnect

### FR-02: Token Lifecycle
- System MUST auto-refresh access tokens before every API request
- System MUST detect expired refresh tokens and throw AuthenticationException
- System MUST NOT make API calls with expired access tokens

### FR-03: Multi-Company
- System MUST support N simultaneous QBO company connections
- System MUST isolate tokens per company
- System MUST route API calls to the correct company endpoint

### FR-04: Multi-Tenant
- System MUST scope company resolution and token access by tenant_id
- System MUST prevent cross-tenant token access
- System MUST support per-tenant DB (stancl/tenancy) without configuration

### FR-05: Source Agnostic
- System MUST accept any Eloquent model as a company source
- System MUST store source_type and source_id polymorphically
- System MUST generate a stable UUID (qb_company_id) per registered source

### FR-06: Company Resolvers
- System MUST support env, model, static, and chain resolver drivers
- System MUST allow runtime resolver swapping via middleware
- System MUST validate company IDs against the active resolver

### FR-07: Resources
- System MUST support CRUD for Invoice, Customer, Payment, Account
- System MUST support sparse updates
- System MUST support QuickBooks SQL-like queries
- System MUST support raw GET and POST requests for unlisted endpoints

---

## 7. Non-Functional Requirements

| Requirement | Target |
|-------------|--------|
| PHP Version | 8.1+ |
| Laravel Version | 10.x, 11.x, 12.x |
| Response Handling | Guzzle 7.x |
| Token refresh latency | < 500ms per refresh call |
| Test coverage | > 80% |
| Documentation | PHPDoc on all public methods |

---

## 8. Constraints & Assumptions

- QBO access tokens expire in 3,600 seconds (1 hour)
- QBO refresh tokens expire in approximately 100 days
- Each QBO company requires a separate OAuth2 authorization — one realmId per company
- The consuming application is responsible for redirecting users through the OAuth flow
- `quickbooks_companies` and `quickbooks_tokens` tables are always in the primary DB connection

---

## 9. Dependencies

| Dependency | Version | Purpose |
|------------|---------|---------|
| `guzzlehttp/guzzle` | ^7.0 | HTTP client for API calls |
| `illuminate/support` | ^10\|^11\|^12 | Laravel base |
| `illuminate/database` | ^10\|^11\|^12 | Token store |
| `illuminate/cache` | ^10\|^11\|^12 | Cache token store |
| `nesbot/carbon` | ^2\|^3 | Token expiry timestamps |

---

## 10. Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Intuit OAuth endpoint change | Low | High | Abstract endpoints in OAuth2Handler constants |
| Refresh token expiry causes downtime | Medium | High | Monitor expiry dates, alert before expiry |
| Source model renamed/deleted | Low | Medium | qb_company_id UUID remains stable regardless |
| Multi-tenant token leak | Low | Critical | tenant_id scope enforced at store layer |
| Laravel major version break | Medium | Medium | Support matrix tested per major version |
