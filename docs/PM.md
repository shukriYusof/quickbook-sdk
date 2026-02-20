# Project Management Document (PM)
## QuickBooks Online Composer SDK
**Version:** 1.0.0
**Status:** In Development
**Last Updated:** 2026-02-20
**Project Manager:** —

---

## 1. Project Overview

| Item | Detail |
|------|--------|
| Project Name | QuickBooks Online Composer SDK |
| Type | Internal Composer Package / Open Source Library |
| Stack | PHP 8.1+, Laravel 10/11/12, MySQL, Redis |
| Start Date | 2026-02-20 |
| Target Release | 2026-03-20 (v1.0.0) |
| Repository | `yourvendor/quickbooks-sdk` |

---

## 2. Deliverables

| Deliverable | Description | Status |
|-------------|-------------|--------|
| Core SDK Package | Full Composer package with all src files | 🟡 In Progress |
| Database Migrations | quickbooks_companies + quickbooks_tokens | ✅ Done |
| Token Stores | Database + Cache drivers | ✅ Done |
| Company Resolvers | env, model, static, chain drivers | ✅ Done |
| OAuth2 Handler | Full OAuth2 flow | ✅ Done |
| Resources | Invoice, Customer, Payment, Account | ✅ Done |
| Laravel Integration | ServiceProvider + Facade + Config | ✅ Done |
| Artisan Command | qb:register | ✅ Done |
| Multi-tenant Support | TenantDatabaseTokenStore + middleware | ✅ Done |
| Polymorphic Bridge | QuickBooksCompany model | ✅ Done |
| Unit Tests | PHPUnit test suite | 🔴 Pending |
| PRD.md | Product requirements | ✅ Done |
| BA.md | Business analysis | ✅ Done |
| PM.md | Project management | ✅ Done |
| SKILLS.md | Technical reference | ✅ Done |
| ARCHITECTURE.md | System design | ✅ Done |
| CHANGELOG.md | Release notes | 🔴 Pending |
| README.md | Public-facing documentation | 🔴 Pending |

---

## 3. Milestones

| Milestone | Target Date | Status |
|-----------|-------------|--------|
| M1: Core architecture complete | 2026-02-20 | ✅ Done |
| M2: Multi-company + multi-tenant | 2026-02-25 | 🟡 In Progress |
| M3: Full test suite | 2026-03-05 | 🔴 Pending |
| M4: Documentation complete | 2026-03-10 | 🟡 In Progress |
| M5: v1.0.0 release | 2026-03-20 | 🔴 Pending |

---

## 4. Work Breakdown Structure

### Phase 1 — Foundation (Completed)
- [x] Package scaffolding (composer.json, autoload, PSR-4)
- [x] OAuth2Handler (auth URL, token exchange, refresh, revoke)
- [x] TokenStoreInterface contract
- [x] DatabaseTokenStore
- [x] CacheTokenStore
- [x] QuickBooksClient (request routing, auto-refresh)
- [x] QuickBooksManager (multi-company hub)
- [x] Laravel ServiceProvider + Facade
- [x] Base config file

### Phase 2 — Multi-Company & Resolvers (Completed)
- [x] CompanyResolverInterface contract
- [x] EnvResolver
- [x] ModelResolver
- [x] StaticResolver
- [x] ChainResolver
- [x] ResolverFactory
- [x] Manager integration with resolvers
- [x] .env driven company definitions

### Phase 3 — Multi-Tenant & Source Agnostic (Completed)
- [x] quickbooks_companies bridge table migration
- [x] quickbooks_tokens table migration (FK to bridge)
- [x] QuickBooksCompany Eloquent model
- [x] QuickBooksToken Eloquent model
- [x] TenantDatabaseTokenStore
- [x] Polymorphic registerSource() factory
- [x] findBySource() helper
- [x] SetQuickBooksTenantContext middleware
- [x] qb:register Artisan command

### Phase 4 — Resources (Completed)
- [x] BaseResource (find, all, query, create, update, sparseUpdate, delete)
- [x] Invoice (+ send, void, overdue)
- [x] Customer (+ active, findByEmail)
- [x] Payment
- [x] Account (+ getByType)

### Phase 5 — Testing (Pending)
- [ ] Unit: OAuth2Handler
- [ ] Unit: DatabaseTokenStore
- [ ] Unit: CacheTokenStore
- [ ] Unit: EnvResolver / ModelResolver / ChainResolver
- [ ] Unit: QuickBooksClient token refresh logic
- [ ] Feature: Full OAuth2 flow
- [ ] Feature: Multi-company isolation
- [ ] Feature: Tenant isolation
- [ ] Feature: registerSource() idempotency

### Phase 6 — Documentation & Release (In Progress)
- [x] PRD.md
- [x] BA.md
- [x] PM.md
- [x] SKILLS.md
- [x] ARCHITECTURE.md
- [x] AGENT.md
- [ ] CHANGELOG.md
- [ ] README.md (public)
- [ ] Packagist submission
- [ ] GitHub Actions CI pipeline

---

## 5. Task Tracking

### Active Tasks

| ID | Task | Priority | Assignee | ETA |
|----|------|----------|----------|-----|
| T-01 | Write PHPUnit tests for OAuth2Handler | High | Dev | 2026-03-01 |
| T-02 | Write PHPUnit tests for token stores | High | Dev | 2026-03-01 |
| T-03 | Write feature test for multi-company isolation | High | Dev | 2026-03-03 |
| T-04 | Write README.md | Medium | Dev | 2026-03-10 |
| T-05 | Set up GitHub Actions CI | Medium | Dev | 2026-03-10 |
| T-06 | Add Vendor, Bill resources | Low | Dev | 2026-03-15 |
| T-07 | Packagist submission | Medium | Dev | 2026-03-18 |

---

## 6. Definition of Done

A feature is considered done when:
- [ ] Code is implemented and follows PSR-12
- [ ] PHPDoc added to all public methods
- [ ] Unit or feature test written and passing
- [ ] No breaking changes to existing public API
- [ ] Relevant documentation updated (SKILLS.md or README.md)
- [ ] Peer reviewed via merge request

---

## 7. Versioning Strategy

Follows **Semantic Versioning (SemVer)**:

| Version | Meaning | Example |
|---------|---------|---------|
| MAJOR | Breaking API changes | 2.0.0 |
| MINOR | New backward-compatible features | 1.1.0 |
| PATCH | Bug fixes | 1.0.1 |

---

## 8. Branching Strategy

```
main          → stable release branch (tagged)
develop       → integration branch
feature/*     → individual features (merged to develop)
hotfix/*      → critical fixes (merged to main + develop)
```

---

## 9. Risk Register

| ID | Risk | Probability | Impact | Owner | Mitigation |
|----|------|-------------|--------|-------|------------|
| R-01 | Intuit OAuth API changes | Low | High | Dev | Abstract all endpoints as constants |
| R-02 | Refresh token silent expiry | Medium | High | Dev | Token expiry monitoring + alerting |
| R-03 | Multi-tenant token leak | Low | Critical | Dev | Enforced tenant_id scoping at store layer |
| R-04 | Laravel major version incompatibility | Medium | Medium | Dev | Composer constraint + CI matrix |
| R-05 | Missing test coverage causes production bug | Medium | High | Dev | 80% coverage gate in CI |

---

## 10. Communication

| Audience | Medium | Frequency |
|----------|--------|-----------|
| Engineering team | Git commits + MR descriptions | Per feature |
| Project stakeholders | PM.md updates | Per milestone |
| External consumers | README.md + CHANGELOG.md | Per release |
| AI agents / copilots | AGENT.md + SKILLS.md | On change |
