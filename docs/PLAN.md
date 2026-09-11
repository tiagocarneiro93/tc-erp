# tc-erp — Implementation Plan

This plan turns `docs/technical-scope.md` into ordered, verifiable work. It is written for Claude Code working with the owner (Tiago).

## How to use this plan

- Phases are done in order. Within a phase, tasks are done in order unless marked *(parallel)*.
- **Phase 0 is detailed here.** For every later phase, Claude Code first writes `docs/plans/phase-N.md`, splitting the phase into session-sized tasks with acceptance criteria and tests, and waits for the owner's approval before coding.
- Tasks marked **🧑 Owner** are done by Tiago (external accounts, legal documents, credentials, reviews). Claude Code must not attempt them, but should remind the owner when one blocks progress.
- A task is done when its acceptance criteria are met and `make lint` and `make test` pass. Tick the box and add a short note if anything deviated.
- References like (§7.1) point to sections of the technical scope.

---

## Owner checklist (start now, external lead times)

- [ ] 🧑 Download the legal and technical sources listed in `docs/legal/README.md` into `docs/legal/` (needed from Phase 1 for reference data, blocking for Phase 2).
- [ ] 🧑 Shortlist qualified trust service providers for the electronic seal; request API documentation and a sandbox (needed in Phase 3).
- [ ] 🧑 Request access to the AT webservices test environment and the required certificates (needed in Phase 3).
- [ ] 🧑 Confirm requirements for submitting the certification request (Modelo 24) as TCWeb (needed in Phase 6).
- [ ] 🧑 Create the Git repository (GitHub assumed) and give Claude Code access to work locally.

---

## Phase 0 — Foundations

**Goal:** a running monorepo where a user can log in, create companies, switch between them, and where company isolation via PostgreSQL RLS is proven by tests. CI enforces quality and architecture from day one.

### 0.1 Monorepo skeleton
- [x] Create `/api`, `/web`, `/docker`, `/docs`, root `README.md`, `.editorconfig`, `.gitignore` (including `.env.local`, `var/`, `node_modules/`, `*.pem`, `docker/keys/`).
- [x] `Makefile` with the targets listed in `CLAUDE.md` (stubs allowed at first; each becomes real in the task that needs it).

**Accept:** repository structure matches `CLAUDE.md`; `make help` lists targets.

### 0.2 Docker environment
- [x] `docker-compose.yml` with: `php` (FrankenPHP, PHP 8.4, required extensions: intl, pdo_pgsql, bcmath, sodium, openssl, zip, opcache, xdebug optional), `postgres` (17), `redis`, `mailpit`, `minio` (S3), `node` (for `/web`).
- [x] Healthchecks on all services; named volumes for data.
- [x] `docker/postgres/init/` script creating roles: `app_owner` (owns schema, runs migrations) and `app_runtime` (login role, no ownership, no `BYPASSRLS`), plus a separate test database with the same roles.
- [x] Default privileges so tables created by `app_owner` grant `app_runtime` only what the scope allows (decided per table in migrations; default: `SELECT, INSERT, UPDATE, DELETE` for normal tables, restricted later for fiscal tables).

**Accept:** `make up` starts everything healthy; `psql` as `app_runtime` cannot create tables; Mailpit and MinIO UIs reachable.

**Note:** built and validated in a sandbox without a Docker daemon (`docker compose config` validated, `make help` verified), so `make up` itself is unverified — please confirm it starts all six services healthy on your machine before we move on. The `php` service's healthcheck is a placeholder TCP check; task 0.3 will point it at the real `/api/v1/health` endpoint.

### 0.3 Symfony application
- [x] Symfony 7.4 skeleton in `/api` with: ORM pack (Doctrine, migrations), Messenger, Security, Validator, Serializer, Uid, Monolog, NelmioApiDocBundle, `brick/math`, `brick/money`, Symfony Mailer, rate limiter.
- [x] `declare(strict_types=1);` everywhere. (All app code written so far has it; task 0.4's PHP-CS-Fixer `declare_strict_types` rule enforces it repo-wide from then on, including recipe-managed boilerplate.)
- [x] Two DB connection URLs: `DATABASE_URL` (as `app_runtime`, used by the app) and `DATABASE_MIGRATIONS_URL` (as `app_owner`, used only by `make migrate`).
- [x] Health endpoint `GET /api/v1/health` (checks DB and Redis).

**Accept:** health endpoint returns 200 in Docker; migrations run as owner, app runs as runtime role.

**Note:** verified locally against real PostgreSQL/Redis instances in this sandbox (no Docker daemon available here — see task 0.2's note); `docker-compose.yml`'s `php` service env vars and healthcheck were updated to match. Please confirm `make up` + `curl http://localhost:8080/api/v1/health` on your machine. `doctrine:migrations:status` confirmed the `migrations` connection reaches Postgres as `app_owner`; `psql` as `app_runtime` still cannot `CREATE TABLE` (re-verified after wiring the real app, not just the container).

### 0.4 Quality tooling and CI
- [x] PHP-CS-Fixer (PER-CS + Symfony rules), PHPStan level max with `phpstan-symfony` and `phpstan-doctrine`, PHPUnit, Deptrac.
- [x] Custom PHPStan rule (or tagged check) forbidding `float` type declarations in `*/Domain/*` of Tax, Fiscal, Inventory and Accounts modules.
- [x] GitHub Actions workflow: backend (cs check, PHPStan, Deptrac, PHPUnit with a PostgreSQL service), frontend (added in 0.13), OpenAPI drift check (added in 0.11), RLS schema check (added in 0.9).

**Accept:** CI is green on an empty-but-configured project; a deliberately introduced Deptrac violation fails CI (then removed).

**Notes:** all four tools verified locally (real Postgres/Redis, no Docker daemon in this sandbox — see task 0.2/0.3 notes); deliberately introduced a `Domain -> Infrastructure` dependency and confirmed Deptrac reports it and exits non-zero, then removed it. Used `deptrac/deptrac` rather than `qossmic/deptrac` (the latter is marked abandoned upstream in favour of the former). The Deptrac ruleset here only enforces layering direction (Domain/Application/Infrastructure/UI generically); per-module cross-boundary isolation is refined in task 0.5 once real modules exist. GitHub Actions workflow (`.github/workflows/backend.yml`) is written but unverified against real GitHub Actions runners — please confirm it goes green on a PR.

### 0.5 Module structure and architecture rules
- [x] Create `src/Shared` and `src/Platform` with `Domain/`, `Application/`, `Infrastructure/`, `UI/Http/`.
- [x] Deptrac layers and rules as in `CLAUDE.md` (Domain independent of frameworks; modules isolated).
- [x] Doctrine configured for XML mapping from `src/*/Infrastructure/Persistence/Doctrine/Mapping`.
- [x] Messenger buses: `command.bus` (sync, with `doctrine_transaction` middleware), `query.bus` (sync), `event.bus`; async transport on Redis (used from Phase 3).
- [x] ADR `docs/decisions/0001-modular-monolith-hexagonal.md` summarising the architecture.

**Accept:** a sample command/handler in Platform runs through the bus inside a transaction (covered by a test).

**Notes:** Deptrac can't parametrise layers by module name, so cross-module isolation uses one layer per (module, tier) pair — see the ADR for the pattern to copy when a new module is added. `HealthController` (task 0.3) moved into `Platform/UI/Http/` now that the module structure exists. Re-verified Deptrac catches a real `Platform.Domain -> Platform.Infrastructure` violation under the new ruleset (and doesn't false-flag on vendor/framework dependencies, which land as "uncovered", not "violation").

### 0.6 Shared kernel
- [x] `Clock` interface + system implementation + frozen test clock.
- [x] UUID v7 identifiers (typed IDs per aggregate, e.g. `CompanyId`, `UserId`).
- [x] `Nif` value object with Portuguese check-digit validation (test-first, with valid/invalid examples).
- [x] Decimal helpers around `brick/math` (`Decimal`, `Money` in EUR, `Quantity`, `Percentage`), with string (de)serialisation for the API.

**Accept:** unit tests for all value objects; no `float` anywhere in `Shared/Domain`.

**Notes:** `CompanyId`/`UserId` (concrete `AbstractUuidId` subclasses) live in `Platform/Domain` since they're Platform-specific, not generic Shared concepts — 0.7 will use them on the `companies`/`users` entities. `Nif` deliberately validates only the modulus-11 check digit, not the legal list of valid leading-digit categories (a separate, more volatile rule, not asked for here). "String (de)serialisation" is `fromString()`/`toString()` round-tripping; wiring these into request/response DTOs happens as real endpoints are built. 25 unit tests, all green; `grep -rn '\bfloat\b' src/Shared/Domain/` finds none outside a comment.

### 0.7 Platform domain and persistence
- [x] Global tables (§6.1): `users`, `companies`, `memberships`, `roles`, `role_permissions`, `api_tokens` (structure only for now), `signing_keys` (structure only).
- [x] Seed roles and permissions (owner, admin, billing, stock, accountant, read_only) — permission list documented in `docs/decisions/0002-roles-and-permissions.md`.

**Accept:** migrations run; repositories covered by integration tests against PostgreSQL.

**Notes:** resolved a real inconsistency in technical-scope.md: §5.1 lists API tokens as global (no `company_id`), but §6.1's `api_tokens` column list includes one. Kept `company_id` (matches §6.1 and §9.2's "company-scoped API tokens") but did **not** add RLS — token lookup during authentication has to work before any company context exists, so it can't fail-closed on a missing one; documented the reasoning on `ApiToken`. Introduced two Doctrine entity managers (`default` on `app_runtime`, `migrations` on `app_owner`, same mappings) after discovering `doctrine:migrations:diff` needs an entity manager to compare against — pinning only a DBAL `connection:` in `doctrine_migrations.yaml` (my original 0.5 setup) silently drops the schema provider. Added a `jsonb` Doctrine type (Postgres `JSONB`, not plain `JSON`) since it recurs across most future modules. `ApiToken`/`SigningKey` are structure-only, no repository yet, matching the "structure only for now" scope. 36 tests total (11 new integration tests against real Postgres, wrapped per-test in a rolled-back transaction); Deptrac, PHPStan and CS-Fixer all still green; no Doctrine/Symfony import anywhere in `Platform/Domain`.

### 0.8 Authentication
- [ ] Session-based JSON login for the SPA (`json_login`), logout, `GET /api/v1/me` (user, companies with role, permissions).
- [ ] Cookies: httpOnly, Secure, SameSite=Lax; CSRF protection for state-changing requests (e.g. custom header check + SameSite).
- [ ] Password rules (§8.1): hashing with Symfony defaults; `must_change_password` enforced on first login; password cannot be empty; no endpoint ever returns or lets admins set a known password (invites and resets by email link only).
- [ ] Password reset by email (Mailpit in dev); login rate limiting.
- [ ] Functional tests for all of the above.

**Accept:** a seeded user logs in, is forced to change the password, then accesses `/me`.

### 0.9 Company context and Row-Level Security
- [ ] `CompanyContext` service; request listener resolving `{companyId}` from company routes; `CompanyVoter` checking membership and permissions; 404/403 behaviour documented.
- [ ] DBAL middleware: at the beginning of every transaction, when a company context is set, run `SELECT set_config('app.company_id', :id, true)`.
- [ ] Company-scoped work always runs inside a transaction (command bus middleware; a `CompanyQueryRunner` wrapper for reads).
- [ ] Migration helper `enableCompanyIsolation(table)` creating `ENABLE` + `FORCE ROW LEVEL SECURITY` and the `company_isolation` policy (§5.2).
- [ ] First company-scoped table: `audit_log` (§6.12), insert-only for `app_runtime` (no UPDATE/DELETE grants), written through an `AuditLogger` service.
- [ ] CI schema check (SQL query or PHPUnit test): every table with a `company_id` column has RLS enabled, forced, and a policy.
- [ ] Isolation tests: with company A in context, reading/inserting/updating company B rows fails or returns nothing; with **no** context, queries on company tables error (fail closed) — including after a previous transaction on the same connection set a value.
- [ ] Messenger `CompanyStamp` + middleware restoring/clearing the context in workers (tested with an in-memory transport).

**Accept:** all isolation tests pass; removing the policy from `audit_log` makes the schema check fail.

### 0.10 Company onboarding and switching
- [ ] `CreateCompany` use case (§5.5): validates NIF, creates company, owner membership, default settings placeholder; one transaction; audit entry.
- [ ] Endpoints: `POST /api/v1/companies`, `GET /api/v1/companies` (mine), invite user to company (email link), change member role, remove member.

**Accept:** functional tests covering creation, listing, invitations and permission checks.

### 0.11 API conventions
- [ ] Problem Details (RFC 9457) exception listener with stable `type` codes; validation errors mapped with field paths.
- [ ] Cursor pagination helper and response envelope for lists.
- [ ] Nelmio configured (`/api/doc` in dev); `make openapi` dumps `api/openapi.json`.
- [ ] CI check: `api/openapi.json` is up to date (fails if endpoints changed without regenerating).
- [ ] `Idempotency-Key` infrastructure (table + middleware/attribute) ready for Phase 2, with tests (same key + same payload returns stored response; same key + different payload → 422).

**Accept:** all endpoints documented; error format covered by tests.

### 0.12 Development fixtures
- [ ] `make seed`: demo users (owner, accountant with two companies), two demo companies with valid test NIFs.

**Accept:** fresh `make up && make migrate && make seed` gives a usable demo.

### 0.13 Web application shell *(parallel with 0.10–0.12 once 0.8 exists)*
- [ ] Vite + React + TypeScript strict in `/web`, pnpm, ESLint, Prettier, Vitest, Playwright.
- [ ] Tailwind + shadcn/ui; base layout (sidebar, header, company switcher), pt-PT formatting utilities (`formatMoney`, `formatQuantity`, `formatDate` from strings, no floats).
- [ ] orval generating the client + TanStack Query hooks from `api/openapi.json`.
- [ ] TanStack Router: `/login`, `/change-password`, `/c/$companyId/…` protected routes, 403/404 pages.
- [ ] Screens: login, forced password change, password reset, company list/create, company switcher, members management.
- [ ] CI frontend job: lint, `tsc --noEmit`, Vitest, Playwright e2e (login → create company → switch company) against the Docker stack.

**Accept:** e2e passes in CI.

### Phase 0 exit criteria
- [ ] Log in, create and switch companies, invite a member — in the browser.
- [ ] RLS isolation and fail-closed tests pass; schema check enforced in CI.
- [ ] CI green: CS, PHPStan max, Deptrac, PHPUnit, OpenAPI drift, frontend lint/types/tests/e2e.
- [ ] 🧑 Owner review of Phase 0 before starting Phase 1.

---

## Phase 1 — Master data (§6.2–6.5, §7.9.8, §7.10)

Prerequisite: 🧑 legal sources for tax rates and exemption reasons in `docs/legal/`.

Scope for `docs/plans/phase-1.md`:
- Global reference data with versioned seeds: document types (incl. OR, PF, NE), tax rates for PT, PT-AC, PT-MA with validity dates, exemption reasons (M codes), units, countries. 🧑 Owner verifies the seed data against official sources.
- Company profile and fiscal settings (`company_profile`, `settings`), encrypted AT credentials storage (no AT calls yet).
- Customers and suppliers (NIF validation, final consumer customer, addresses).
- Products: kinds `simple`/`kit`, SAF-T product type, units, families, default tax rate and exemption reason, `track_stock`.
- Price lists and product prices stored **as entered** with `includes_vat` (§7.9.8); live display of the other value via a calculation endpoint (uses a first, minimal version of `PriceCalculator` for unit conversion only).
- Kit composition (`product_components`), no nesting, cycle-free, informational VAT-rate warning.
- Warehouses (default warehouse created at onboarding).
- Web: list screens (TanStack Table, cursor pagination, filters, search) and forms (React Hook Form + Zod) for all of the above.

Exit: full CRUD via API and web; isolation tests for every new company table; OpenAPI and client regenerated.

---

## Phase 2 — Fiscal core (§6.6–6.9, §7.1–7.4, §7.6, §7.9)

**Blocking prerequisites:**
- 🧑 Despacho 8632/2014, ATCUD/QR code Portaria and AT QR specification, SAF-T technical notes in `docs/legal/`.
- 🧑 Decisions in scope §14.2 confirmed (defaults are recommended values).
- 🧑 Owner reviews the initial pricing test vectors before the calculator is considered done.

Scope for `docs/plans/phase-2.md` (suggested order):
1. `PriceCalculator` (test-first): net/gross modes, per-line and per-group rounding, line discounts (%, cascading %, fixed), global discount allocation (largest remainder), VAT last, precision tiers; `pricing-test-vectors.json`; `/calculate` endpoint.
2. Series: entity, lifecycle states (without AT calls: validation code entered manually in dev), training series.
3. Drafts: create/update/delete, `calculate`, validation rules.
4. Fiscal tables with immutability: grants, triggers (§6.9), status events; DB integrity tests.
5. `DocumentSigner` port + OpenSSL adapter (dev key generated locally, git-ignored); signing string exactly per Despacho (**[VERIFY]** resolved from `docs/legal/`); golden-file tests.
6. Issuance use case (§7.1) end to end: series lock, chronology, canonical calculation, signing, ATCUD, QR payload, inserts (with customer and issuer snapshots and template version), series update, idempotency, audit; concurrency test (parallel issuance, no gaps/duplicates, valid chain).
7. Document types FT, FS, FR, NC, ND; references for NC/ND; credit note from document.
8. Working documents OR, PF, NE (§6.8) and conversions (full and partial) with pending quantities.
9. Receipts (RG) with allocations to open invoices.
10. Cancellation (status `A`) with legal conditions (**[VERIFY]**), compensating entries prepared for stock/accounts (wired in Phase 5).
11. Web: document editor (keyboard-friendly lines, net/gross switch, live server totals), document list and detail, conversion and credit-note actions, receipts, on-screen draft preview (not printable).

Exit: all fiscal test families green (golden, concurrency, integrity, vectors); no document can be modified after issuance by any path; 🧑 owner review of the signing and calculation code.

---

## Phase 3 — Output and AT compliance (§7.5, §7.7, §7.8)

Prerequisites: 🧑 trust service provider sandbox; 🧑 AT test environment access and certificates; 🧑 SAF-T XSD and AT webservice manuals in `docs/legal/`.

Scope for `docs/plans/phase-3.md`:
- `DocumentPdfRenderer` port with on-demand rendering (§7.8): spike both engines (mPDF vs Twig → Gotenberg) on the invoice template, 🧑 owner picks one; versioned templates with all legal mentions (hash characters + certification mention, ATCUD, QR image, "Este documento não serve de fatura" for working documents); rendering from stored data and snapshots only; `document_prints` log and copy mentions.
- Object storage (MinIO/S3) via `stored_files` for sealed PDFs, SAF-T files and attachments only, SHA-256, retention metadata.
- `ElectronicSealer` port: fake adapter for dev/tests, provider adapter for the chosen trust service provider.
- Email sending of documents (async).
- SAF-T (PT) streaming generator + XSD validation in app and CI.
- AT webservice clients: series register/finish/consult, invoice communication; SOAP security per manual; AT outbox (`at_communications`), async processing, retries, sweeper (§5.4, §7.5); AT status UI.
- Series lifecycle with real AT validation codes (test environment).

Exit: documents communicated to the AT test environment; SAF-T validates against XSD; sealed PDFs produced in sandbox.

---

## Phase 4 — Movement of goods (§7.3)

Scope for `docs/plans/phase-4.md`: GT, GR, GD with loading/unloading data; synchronous AT transport communication with AT code on the document; failure handling and retry UI; conversion GR → FT without double stock movement (flag prepared for Phase 5).

Exit: transport documents obtain AT codes in the test environment.

---

## Phase 5 — Stock, purchases and current accounts (§6.7, §6.10, §6.11, §7.10)

Scope for `docs/plans/phase-5.md`:
- Stock ledger (`stock_movements`, insert-only), `stock_levels` projection with row locks, weighted average cost.
- Stock effects of issued documents (sales, credit notes, GR/GT/GD, cancellations via compensating movements), kits exploded into components with `source_line_id`.
- Adjustments, transfers, stock counts; negative stock policy setting.
- Purchases: supplier documents (post → stock in + supplier account entry), supplier payments, audit of corrections.
- Current accounts for customers and suppliers: entries, allocations, balances, statements.
- Annual inventory export if decided (§14.4).
- Web screens for all of the above; dashboard (sales, VAT, open balances, stock).

Exit: stock and accounts reconcile with documents in automated tests.

---

## Phase 6 — Hardening and certification

Scope for `docs/plans/phase-6.md`: MFA (TOTP); security review (OWASP ASVS checklist); performance tests against targets (§3.2); backup/restore drill; operational docs; production signing key procedure (🧑 owner); certification dossier and Modelo 24 (🧑 owner, Claude Code prepares technical documentation); conformity test support.

Exit: 🧑 **AT certificate obtained.**

---

## Phase 7 — Pilot and launch

Scope for `docs/plans/phase-7.md`: data import tools (customers, products, opening balances, stock), production infrastructure (§10.2), monitoring and alerts, subscription plans and limits, onboarding flow, pilot with 1–3 companies.

Exit: first paying customers.

---

## After v1 (not planned in detail)

CIUS-PT structured e-invoicing (B2G), POS, assembled products and production, lot traceability, integrations (e-commerce), MCP server.
