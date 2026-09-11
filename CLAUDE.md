# CLAUDE.md — tc-erp

Instructions for Claude Code working in this repository. Read this file fully before any task.

## What this project is

tc-erp (codename) is a multi-tenant SaaS invoicing and ERP platform for Portuguese SMEs, to be **certified by the Autoridade Tributária (AT)**. It issues fiscally relevant documents (invoices, credit notes, receipts, transport documents, quotes, proformas, orders), manages stock and purchases, exports SAF-T (PT) and communicates with AT webservices.

- **Source of truth for design:** `docs/technical-scope.md`
- **What to build and in which order:** `docs/PLAN.md`
- **Detailed plan for the current phase:** `docs/plans/phase-N.md` (you write it, the owner approves it)
- **Architecture decisions:** `docs/decisions/NNNN-title.md` (ADR format)
- **Legal and technical sources from the AT:** `docs/legal/`

Owner: Tiago Carneiro (TCWeb). He is an experienced developer who has built an AT-certified invoicing product before. Ask him when something is ambiguous; do not guess.

## Stack

- Backend: PHP 8.4, Symfony 7.4 LTS, Doctrine ORM (XML mapping), Symfony Messenger, NelmioApiDocBundle, PostgreSQL 17+, Redis.
- Frontend: Vite, React, TypeScript (strict), TanStack Router, TanStack Query, TanStack Table, React Hook Form + Zod, shadcn/ui + Tailwind, ECharts, API client generated with orval from the OpenAPI spec.
- Money: `brick/math` and `brick/money`. **Never floats.**
- Local environment: Docker Compose. CI: GitHub Actions (ask before assuming otherwise).

## Repository layout

```
/api        Symfony application (the core)
/web        React SPA
/docs       scope, plan, phase plans, ADRs, legal sources
/docker     Dockerfiles, init scripts (DB roles), config
Makefile    entry point for all common commands
docker-compose.yml
```

## Commands

Use the Makefile; if a command you need does not exist, add it to the Makefile.

```
make up              start the environment
make down            stop it
make api-shell       shell in the PHP container
make test            all tests (api + web)
make test-api        PHPUnit
make test-web        Vitest
make e2e             Playwright
make lint            PHP-CS-Fixer (check), PHPStan, Deptrac, ESLint, tsc
make fix             auto-fix code style
make migrate         run Doctrine migrations (as the migration owner role)
make openapi         dump OpenAPI spec to api/openapi.json and regenerate the web client
make seed            load development fixtures
```

## Workflow rules

1. **One task at a time**, in the order of `docs/PLAN.md`. Do not start the next task until the current one meets its acceptance criteria.
2. **At the start of each phase**, write `docs/plans/phase-N.md` breaking the phase into small tasks (each doable in one session, with acceptance criteria and tests). **Stop and ask the owner to approve it** before coding.
3. Before finishing any task: `make lint` and `make test` must pass. Never skip, disable or weaken tests or static analysis to make them pass.
4. Tick the task in `docs/PLAN.md` and note anything relevant (deviations, follow-ups).
5. Small commits, Conventional Commits (`feat(fiscal): …`, `fix(inventory): …`, `test(tax): …`). Do not push unless asked.
6. Design changes: never silently diverge from `docs/technical-scope.md`. Propose the change, and once agreed, update the scope and write an ADR if the decision is significant.
7. Never edit a migration that has been committed; write a new one.

## Hard rules — fiscal compliance

These exist because the product must pass AT certification. Breaking them is never acceptable, even temporarily.

- **Never guess legal or AT technical formats.** Items marked **[VERIFY]** in the scope (signing string format, QR code fields, SAF-T fields and precision, webservice specs, document mentions, rounding mode, cancellation rules…) must be resolved from the documents in `docs/legal/`, citing the document and section in code comments or the phase plan. If the source is missing or unclear, **stop and ask the owner**.
- **Issued fiscal data is immutable.** No code path may UPDATE or DELETE issued documents, lines, receipts, stock movements or audit entries, except the explicitly allowed status/communication columns (scope §6.9). This is enforced by database permissions and triggers — never grant the runtime role more privileges to work around it.
- **Documents are issued only through the issuance use case** (scope §7.1): series lock, chronology checks, canonical calculation, signing, ATCUD, QR, insert, series update, side effects — in one transaction. No shortcuts, no "quick" inserts in fixtures bypassing it (fixtures issue through the use case too).
- **Drafts are not documents.** Drafts are mutable and must never be printable or sendable as a document.
- **All calculations go through `PriceCalculator`** (scope §7.9). No other code computes line amounts, discounts, VAT or totals — not controllers, not Twig, not the frontend. Discounts are applied before VAT; VAT is always calculated last.
- **Never use `float` for money, quantities, prices, rates or percentages** in PHP or TypeScript. Use `BigDecimal`/`Money` in PHP and strings in JSON and TypeScript.
- **The signing private key is never committed**, logged or stored in the database. Development uses a throwaway dev key generated locally (git-ignored). Production key handling is done by the owner.
- **Secrets** (AT credentials, keys, tokens) are encrypted at rest and never appear in logs, exceptions or API responses.

## Hard rules — multi-tenancy (scope §5)

- Every company-scoped table has `company_id UUID NOT NULL`, is created with the RLS helper (`ENABLE` + `FORCE ROW LEVEL SECURITY` + `company_isolation` policy), and has `company_id` as the first column of its unique constraints and main indexes.
- The application connects as `app_runtime` (no table ownership, no `BYPASSRLS`). Migrations run as `app_owner`.
- The company context is set per transaction with `set_config('app.company_id', :id, true)` (equivalent to `SET LOCAL`). Company-scoped work always runs inside a transaction.
- Global tables (users, companies, memberships, reference data) have no `company_id`.
- Every new company-scoped table gets an isolation test; the CI schema check fails if a table with `company_id` has no policy.

## Architecture rules (scope §4)

- Modular monolith. Modules under `api/src/<Module>/`: `Domain/`, `Application/`, `Infrastructure/`, `UI/Http/`.
- `Domain` has no dependency on Symfony, Doctrine or any framework (mapping lives in `Infrastructure` as XML). It may depend on `Shared/Domain` and `brick/*`.
- `Application` contains commands, queries and handlers (use cases); depends on `Domain` only.
- Modules talk to each other through application services or domain events, never through another module's repositories or entities.
- Deptrac enforces this in CI; do not add exceptions to the Deptrac baseline without asking.
- Apply SOLID strictly in Fiscal, Tax, Inventory and Accounts. Simple CRUD areas may be pragmatic — do not create interfaces with a single implementation "just in case" outside the core.
- IDs: UUID v7. Dates/times: `DateTimeImmutable`, stored as `TIMESTAMPTZ` in UTC, obtained through the `Clock` interface (never `new DateTimeImmutable()` in domain or application code).

## API conventions (scope §9)

- Base path `/api/v1`; company routes under `/api/v1/companies/{companyId}/…`.
- Request/response DTOs with `#[MapRequestPayload]` / `#[MapQueryString]` and Symfony Validator; OpenAPI attributes on every endpoint.
- `snake_case` JSON, ISO 8601 dates, decimals as strings.
- Errors as RFC 9457 `application/problem+json` with stable `type` codes.
- Cursor pagination for lists.
- `Idempotency-Key` required on issuing and communicating endpoints.
- After changing endpoints: `make openapi` and commit `api/openapi.json` and the regenerated client.

## Frontend conventions (scope §11)

- Only the generated API client and TanStack Query hooks talk to the API; no hand-written `fetch`.
- No fiscal logic in the browser: totals come from `/calculate`.
- UI text in **European Portuguese (pt-PT)**; code, comments and docs in English.
- Numbers and dates formatted with `Intl` using `pt-PT`.
- Routes include the active company: `/c/$companyId/...`.

## Testing (scope §13)

- Domain logic is written test-first (PHPUnit), especially: `PriceCalculator`, rounding, NIF validation, series rules, chronology, status transitions, signing.
- Integration tests run against real PostgreSQL (Docker), not SQLite.
- Required test families: golden files (hash chain, ATCUD, QR), concurrency (parallel issuance on one series), DB integrity (UPDATE/DELETE on fiscal rows must fail), company isolation, SAF-T XSD validation, pricing test vectors, API functional tests, Playwright e2e for critical flows.
- Pricing test vectors live in `api/tests/Fixtures/pricing-test-vectors.json`; new or changed vectors must be reviewed by the owner.

## When to stop and ask

- A **[VERIFY]** item without a clear answer in `docs/legal/`.
- A **[DECIDE]** item not yet decided in scope §14.
- Any change to fiscal tables, the issuance flow, signing, calculation rules or RLS that is not already described in the scope.
- Anything requiring real credentials, certificates, the production signing key or external accounts.
