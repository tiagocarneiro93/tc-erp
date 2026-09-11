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
- [x] Session-based JSON login for the SPA (`json_login`), logout, `GET /api/v1/me` (user, companies with role, permissions).
- [x] Cookies: httpOnly, Secure, SameSite=Lax; CSRF protection for state-changing requests (e.g. custom header check + SameSite).
- [x] Password rules (§8.1): hashing with Symfony defaults; `must_change_password` enforced on first login; password cannot be empty; no endpoint ever returns or lets admins set a known password (invites and resets by email link only).
- [x] Password reset by email (Mailpit in dev); login rate limiting.
- [x] Functional tests for all of the above.

**Accept:** a seeded user logs in, is forced to change the password, then accesses `/me`.

**Notes:** two genuine Symfony pitfalls worth flagging for future modules. (1) `json_login`'s `check_path` (and `logout`'s `path`) must match an actually-registered route — the `RouterListener` (priority 32) runs before the firewall (priority 8) in the `kernel.request` chain, so the router needs something to match even though the firewall's authenticator intercepts before any controller runs; `AuthController::login()`/`logout()` are dummy methods that only exist for this and throw if ever reached. (2) without an explicit `success_handler`, `JsonLoginAuthenticator::onAuthenticationSuccess()` returns `null` ("let the original request continue"), so a *correct* login silently falls through to that same dummy controller instead of responding — only a custom `AuthenticationSuccessHandlerInterface` (`LoginSuccessHandler`) fixes this; failure already gets a sensible default. Caught both via the functional tests, then confirmed independently with an isolated PHPUnit run and a manual curl against a fresh dev server with SQL-inserted users, to rule out a BrowserKit-specific quirk. `must_change_password` is enforced by a kernel listener (`MustChangePasswordListener`, priority 0, after the firewall) blocking every `/api/v1` route except a small allowlist (`/health`, `/auth/login`, `/auth/logout`, `/auth/change-password`) — `/me` is deliberately **not** allowlisted, so the login response itself carries `must_change_password` instead of relying on a blocked `/me` round-trip. Session cookies: `httponly`, `secure: auto`, `samesite: lax` (`framework.yaml`); CSRF defence is the `X-Requested-With: XMLHttpRequest` header check paired with `SameSite=Lax`, no form tokens (stateless-friendly for a JSON API). Added a `CurrentUserId` port in `Platform/Application/Security` (Symfony-adapter implementation in Infrastructure, aliased public in `services.yaml`) so `AuthController`/`MeController` never reference the Infrastructure `SecurityUser` class directly — Deptrac forbids UI/Http depending on another tier's Infrastructure, and `#[CurrentUser]` needs a concrete `UserInterface` type-hint to get typed access to the domain `User`. Password reset tokens are single-use, SHA-256-hashed at rest, 1-hour TTL, anti-enumeration (same 202 response for known/unknown emails). 43 tests total; Deptrac, PHPStan and CS-Fixer all still green; `Platform/Domain` still has no Doctrine/Symfony import.

### 0.9 Company context and Row-Level Security
- [x] `CompanyContext` service; request listener resolving `{companyId}` from company routes; `CompanyVoter` checking membership and permissions; 404/403 behaviour documented.
- [x] DBAL middleware: at the beginning of every transaction, when a company context is set, run `SELECT set_config('app.company_id', :id, true)`.
- [x] Company-scoped work always runs inside a transaction (command bus middleware; a `CompanyQueryRunner` wrapper for reads).
- [x] Migration helper `enableCompanyIsolation(table)` creating `ENABLE` + `FORCE ROW LEVEL SECURITY` and the `company_isolation` policy (§5.2).
- [x] First company-scoped table: `audit_log` (§6.12), insert-only for `app_runtime` (no UPDATE/DELETE grants), written through an `AuditLogger` service.
- [x] CI schema check (SQL query or PHPUnit test): every table with a `company_id` column has RLS enabled, forced, and a policy.
- [x] Isolation tests: with company A in context, reading/inserting/updating company B rows fails or returns nothing; with **no** context, queries on company tables error (fail closed) — including after a previous transaction on the same connection set a value.
- [x] Messenger `CompanyStamp` + middleware restoring/clearing the context in workers (tested with an in-memory transport).

**Accept:** all isolation tests pass; removing the policy from `audit_log` makes the schema check fail.

**Notes:** `CompanyId` moved from `Platform\Domain` to `Shared\Domain` (with its Doctrine type to `Shared\Infrastructure`) before anything else — every future company-scoped module needs it, and Shared can never depend on Platform (Deptrac), so it has to live where everyone can reach it; `UserId` stayed in Platform since nothing outside it needs one yet. `CompanyContext` (read: `Shared\Domain\Company\CompanyContext`, write: the concrete `RequestCompanyContext`, one instance per request/worker message, `ResetInterface`-tagged for safety under a persistent worker runtime) is deliberately split the same way as `Clock`, so Domain/Application code can depend on "the current company" without depending on how it got there. The `SET LOCAL`-equivalent itself is a DBAL 4 `Driver\Middleware` chain (`CompanyContextMiddleware`/`Driver`/`Connection`) overriding `beginTransaction()`, registered only for the `default` connection (`config/services.yaml`, `autoconfigure: false` + a manual `connection: default` tag — otherwise doctrine-bundle's generic `MiddlewareInterface` autoconfiguration also tags it unscoped, applying it to every connection including `migrations`). Because `set_config(..., true)` only takes effect for the transaction it runs in, it fires once per real transaction — Doctrine's transaction nesting means only the outermost `beginTransaction()` reaches the driver, which is exactly the semantics wanted. `CompanyQueryRunner` (`Shared\Domain\Company`, DBAL-backed) exists for the same reason as the command bus's `doctrine_transaction` middleware: a bare read outside an explicit transaction would lose the company context after one statement. `CompanyRouteListener` (`Platform\Infrastructure\Http`, priority -10, after the firewall and `MustChangePasswordListener`) resolves `{companyId}` via `CompanyVoter`; policy is 404 for both a nonexistent company and one the caller isn't an active member of (never reveal a company's existence to an outsider), reserving 403 for a confirmed member who lacks a specific permission — task 0.10 onward, once there is a permission to check. `audit_log` and `memberships`/`api_tokens` (already global per §5.1) needed a schema-check exemption list for that reason. `audit_log`'s immutability reuses the same mechanism CLAUDE.md requires for fiscal tables later: app_owner's `ALTER DEFAULT PRIVILEGES` grants app_runtime UPDATE/DELETE on every new table, so the migration explicitly `REVOKE`s them (`CompanyIsolationMigration::makeInsertOnly()`); a dedicated integration test confirms both UPDATE and DELETE get a real Postgres permission error. `AuditLogger` takes `userId`/`apiTokenId` as plain strings, not `Platform\Domain` value objects, so Shared never depends on Platform. Isolation tests open their own transactions per scenario rather than reusing `PlatformRepositoryTestCase`'s single-wrapping-transaction pattern: nested/logical transactions only call the driver's `beginTransaction()` for the outermost one, so a context switch mid-transaction would never reach `set_config` — production code has the same constraint. Since `audit_log` is insert-only for the test's own `app_runtime` connection, isolation tests clean up their probe rows via the `migrations` (app_owner) connection instead — itself still subject to `FORCE ROW LEVEL SECURITY` (no `BYPASSRLS`), so cleanup sets a session-level `app.company_id` first. `messenger.yaml` gained a `when@test` override to `in-memory://` for the async transport, letting `CompanyStampTransportTest` prove a stamp survives a real transport round-trip on top of `RestoreCompanyContextMiddlewareTest`'s in-process coverage of the set/clear logic itself; that middleware only acts when a `CompanyStamp` is present, leaving synchronous in-request dispatches (no stamp) alone so the request listener keeps owning that context's lifecycle for the rest of the request. `phpstan.dist.neon` now analyses `migrations/` too, so a migration trait like `CompanyIsolationMigration` doesn't read as an unused trait. 66 tests total; Deptrac, PHPStan and CS-Fixer all still green.

### 0.10 Company onboarding and switching
- [x] `CreateCompany` use case (§5.5): validates NIF, creates company, owner membership, default settings placeholder; one transaction; audit entry.
- [x] Endpoints: `POST /api/v1/companies`, `GET /api/v1/companies` (mine), invite user to company (email link), change member role, remove member.

**Accept:** functional tests covering creation, listing, invitations and permission checks.

**Notes:** no "default settings placeholder" row is created — there is no company-settings table or module yet (that's Phase 2+); `CreateCompanyHandler`'s docblock says so explicitly so it isn't mistaken for an oversight. The interesting problem was RLS: `audit_log`'s `WITH CHECK` needs `app.company_id` to already equal the row being inserted, but that config is only set once, at `beginTransaction()` — and a brand-new company's id can't come from a route parameter the way every other company-scoped write's does. Fixed by generating the `CompanyId` in `CompaniesController` and calling `CompanyContext::set()` *before* dispatching `CreateCompany`, so the id is already in place when the command bus's `doctrine_transaction` middleware opens the transaction; `CreateCompanyHandler` then reads it back via `CompanyContext::companyId()` instead of taking it as a command field. This is also why `set()`/`clear()` moved from `RequestCompanyContext` onto the `CompanyContext` *interface* itself (task 0.9 had them only on the concrete class): a handler needs to depend on a Domain-layer port, never on Shared's Infrastructure. The same trick lets `CompaniesController` and `CompanyUsersController` stay off `Platform\Infrastructure` entirely — a new `PermissionChecker` port (`Platform\Application\Security`, mirroring task 0.8's `CurrentUserId`) lets command handlers enforce the `members.manage` permission themselves via `CompanyVoter`, rather than the controller doing it. Invites reuse the exact `PasswordResetToken`/hash-at-rest mechanism from task 0.8 (a 7-day TTL, not 1 hour) since a brand-new user has no password to log in with yet — CLAUDE.md's "invites and resets by email link only" already anticipates this. An *existing* user added to a second company gets no email at all (they already have a working password); a separate `InvitationMailer` port exists anyway, not reusing `PasswordResetMailer`, since the two are different business events that will want different wording. `MembershipRepository` gained `remove()` — deleting a membership row is ordinary CRUD, not fiscal data, so none of the immutability rules apply. Functional tests cover the full flow plus permission checks (404 for a non-member, 403 for a member without `members.manage`, 409 for a duplicate NIF or a duplicate invite, 422 for an invalid NIF); they also pin down a `KernelBrowser` gotcha worth flagging — it reboots the kernel (a fresh container and EntityManager) before every `request()`, so a repository fetched once and reused across several `request()` calls silently reads back its own stale, pre-reboot identity map instead of what the latest request persisted. New endpoints follow task 0.8's existing (camelCase, not `snake_case`) request-field precedent — the actual `snake_case` JSON convention (CLAUDE.md, scope §9.1) needs a global serializer name converter, which is task 0.11's job; fixing it endpoint-by-endpoint now would leave the API in a worse, half-converted state until then. 73 tests total; Deptrac, PHPStan and CS-Fixer all still green.

### 0.11 API conventions
- [x] Problem Details (RFC 9457) exception listener with stable `type` codes; validation errors mapped with field paths.
- [x] Cursor pagination helper and response envelope for lists.
- [x] Nelmio configured (`/api/doc` in dev); `make openapi` dumps `api/openapi.json`.
- [x] CI check: `api/openapi.json` is up to date (fails if endpoints changed without regenerating).
- [x] `Idempotency-Key` infrastructure (table + middleware/attribute) ready for Phase 2, with tests (same key + same payload returns stored response; same key + different payload → 422).

**Accept:** all endpoints documented; error format covered by tests.

**Notes:** every domain exception now implements a small `ProblemDetails` port (`Shared\Domain\Exception`, just `problemType(): string` + `httpStatus(): int` — no Symfony import, so Domain stays framework-free) and a single `ProblemDetailsExceptionListener` (kernel.exception, scoped to `/api/v1`) turns any of them, plus `#[MapRequestPayload]`'s own `ValidationFailedException`, into `application/problem+json`. This let every controller drop its try/catch-and-format boilerplate from tasks 0.8/0.10 entirely — they just dispatch and let the exception propagate; the listener already unwraps Messenger's `HandlerFailedException` (which, worth flagging, keys its wrapped-exceptions array by *handler class name*, not a numeric index — `$wrapped[0]` silently returns null, `foreach` is the only safe way to get "the first one"). Validation error field paths are converted through the same name converter as the request body (below), so a `legalName` property violation is reported as `legal_name` — matching what the client actually sent, not the PHP property name. `CursorPaginator`/`CursorPage` live in `Shared\Domain\Http`, not Infrastructure: the algorithm touches no framework class, and every module's UI/Http controllers need to depend on it, which Deptrac forbids for another tier's Infrastructure. Wired into `GET /api/v1/companies` as the first real caller; paginating an already-fetched in-memory list is a Phase 0 simplification explicitly called out in the class's own docblock — a large list (documents, stock movements) will need the same contract over a `WHERE id > :cursor ORDER BY id LIMIT :limit` query instead. Nelmio's config and dev-only `/api/doc` route already existed from earlier scaffolding; this task added OpenAPI attributes to every existing endpoint, made `make openapi` actually dump `api/openapi.json` (`nelmio:apidoc:dump`), and added `OpenApiSpecIsUpToDateTest` — an integration test that renders the spec via Nelmio's own `RenderOpenApi` service and diffs it against the committed file — as the CI check, since it just runs inside `vendor/bin/phpunit`. The Idempotency-Key design deviates from PLAN's "middleware/attribute" wording: it's an explicit `IdempotencyKeyGuard` service a controller wraps its own logic with (`$guard->guard($request, $companyId, fn() => ...)`), backed by a real `idempotency_keys` table (company-scoped, RLS'd, *not* insert-only — a future cleanup sweeper will need to delete expired rows). An event-based interception felt like more moving parts for a feature nothing calls yet; explicit wrapping is what Phase 2's actual issuing endpoints will do anyway. Tested directly (no test-only HTTP route) against a real Postgres transaction, matching the pattern from task 0.9's isolation tests. Finally, this task made good on task 0.10's deferral: JSON request fields are genuinely `snake_case` now (CLAUDE.md, technical-scope.md §9.1) via Symfony's built-in `CamelCaseToSnakeCaseNameConverter`, configured once in `framework.yaml` — `legalName` the PHP property, `legal_name` the JSON key, uniformly across every existing and future `#[MapRequestPayload]` DTO. Also fixed along the way: CI (`backend.yml`) never actually ran database migrations before PHPUnit — every integration/functional test added since task 0.7 would have failed against a clean runner; added migration steps for both `tc_erp` and `tc_erp_test`. 85 tests total; Deptrac, PHPStan and CS-Fixer all still green.

### 0.12 Development fixtures
- [x] `make seed`: demo users (owner, accountant with two companies), two demo companies with valid test NIFs.

**Accept:** fresh `make up && make migrate && make seed` gives a usable demo.

**Notes:** a plain `app:seed` console command (`Platform\Infrastructure\Console\SeedDemoDataCommand`), not `doctrine/doctrine-fixtures-bundle` — the fixture list is four rows and a fixed shape (two companies, an owner and an accountant member of both), nothing needing a fixture framework's reference resolution or ordered loading. It builds entities directly through repositories rather than dispatching `CreateCompany`/`InviteUserToCompany`: CLAUDE.md's "issue through the use case, no shortcuts in fixtures" rule is about fiscal documents specifically, and these are global platform rows with none of that immutability machinery — the existing integration tests already build fixtures the same direct way. Idempotent (checks `findByNif`/`findByEmail`/`find` before creating), so a re-run never fails or duplicates rows — verified by a test that runs the command twice. Demo accounts skip `must_change_password`: that flow exists for a real admin-provisioned account with a password only the admin knows, not a documented public demo password. NIFs (500000018, 500000026) are real modulus-11-valid numbers, not placeholders — `Nif::fromString()` would reject anything else. 86 tests total; Deptrac, PHPStan and CS-Fixer all still green.

### 0.13 Web application shell *(parallel with 0.10–0.12 once 0.8 exists)*
- [x] Vite + React + TypeScript strict in `/web`, pnpm, ESLint, Prettier, Vitest, Playwright.
- [x] Tailwind + shadcn/ui; base layout (sidebar, header, company switcher), pt-PT formatting utilities (`formatMoney`, `formatQuantity`, `formatDate` from strings, no floats).
- [x] orval generating the client + TanStack Query hooks from `api/openapi.json`.
- [x] TanStack Router: `/login`, `/change-password`, `/c/$companyId/…` protected routes, 403/404 pages.
- [x] Screens: login, forced password change, password reset, company list/create, company switcher, members management.
- [x] CI frontend job: lint, `tsc --noEmit`, Vitest, Playwright e2e (login → create company → switch company) against a live API (Postgres + Redis services, migrated and seeded, no Docker daemon in this sandbox — same constraint as tasks 0.2–0.4).

**Accept:** e2e passes in CI.

**Notes:** stack matches CLAUDE.md exactly: Vite 8 + React 19 + TS 6 strict (`noUncheckedIndexedAccess` on), TanStack Router (file-based, flat dot-notation filenames, e.g. `_authenticated.c.$companyId.members.tsx`) + TanStack Query, react-hook-form + zod, Tailwind v4 + hand-written shadcn/ui ("new-york"/neutral). `create-vite` scaffolds oxlint by default, not ESLint — removed it and set up a real ESLint v9 flat config + Prettier instead, since CLAUDE.md names ESLint explicitly.

The `shadcn` CLI's `init`/`add` commands call out to `ui.shadcn.com`, which this sandbox's network doesn't allow (regular npm registry access works fine otherwise) — worked around by hand-authoring `components.json`, the theme CSS, `lib/utils.ts` and each primitive (`button`, `input`, `label`, `card`, `dropdown-menu`, `select`) to match shadcn's standard generated output, installing only the underlying npm packages (`clsx`, `tailwind-merge`, `class-variance-authority`, `@radix-ui/react-*`, `lucide-react`) directly. Worth re-trying the CLI directly in a real dev/CI environment where that host isn't blocked, rather than assuming this workaround is required everywhere.

Money/quantity/percentage formatting (`src/lib/format/decimal.ts`) is pure string manipulation — no `Number()`/`parseFloat` anywhere — per CLAUDE.md's "never float" rule; it assumes canonical-precision input from the API (`/calculate` in later phases) and pads/truncates rather than rounds. Covered by 11 Vitest unit tests.

orval needed two non-obvious config overrides to line up with a hand-written mutator (`src/api/http-client.ts`, the one sanctioned hand-written-fetch call site — the mutator itself): `override.fetch.includeHttpResponseReturnType: false` (otherwise every generated type is wrapped as `{data, status, headers}` even though the mutator returns bare data), and omitting `override.query` entirely (setting it explicitly, even to enable both hook kinds, suppressed orval's correct auto-detection of GET-as-`useQuery`). Also found and fixed three endpoints (`GET /me`, `GET /companies`, and the new `GET /companies/{companyId}/users` below) that had a `#[OA\Response]` with no `content:` schema, so orval had nothing to type the response from and generated `Promise<void>` — added full `OA\JsonContent` schemas and regenerated; this is a direct, concrete payoff of task 0.11's "OpenAPI attributes on every endpoint" rule; endpoints without real content schemas silently degrade frontend type safety.

Building the members-management screen surfaced a real backend gap: technical-scope.md §9.3 specifies `GET/POST/PUT /companies/{c}/users` together, but task 0.10 only implemented invite/change-role/remove — no way to list members. Added the missing slice (`MembershipRepository::findByCompany()`, `ListCompanyMembers` query/handler, `CompanyMemberView`, `CompanyUsersController::list()`) following the exact pattern of the existing member-management handlers, extended `CompanyOnboardingFlowTest` to cover it, and regenerated `api/openapi.json`. This fills a gap already specified in the design doc rather than adding new scope; backend quality gates (PHPStan, Deptrac, CS-Fixer, PHPUnit — 86 tests/257 assertions) all re-verified green afterwards.

Two ESLint rules needed adjusting for idioms this stack actually uses everywhere: `@typescript-eslint/no-misused-promises` (`checksVoidReturn: {attributes: false, properties: false}`), since async `onClick`/`onSuccess` handlers passed as JSX attributes or object properties are the standard React Query pattern; and `@typescript-eslint/only-throw-error` turned off globally, since TanStack Router's documented `throw redirect(...)` idiom throws a `Response` object, not an `Error` (confirmed by reading `@tanstack/router-core`'s source), which the rule otherwise flags everywhere a route guard redirects.

End-to-end verification: `e2e/onboarding.spec.ts` runs the full task 0.13 acceptance flow (login → company list shows both demo companies → create a company with a real modulus-11-valid NIF → switch companies via the header dropdown) against live Vite + PHP dev servers and a seeded database (task 0.12), and passes. Also manually walked login → companies → company dashboard → members screen in a real browser (screenshots reviewed, zero console errors) per CLAUDE.md's "use the feature in a browser before reporting complete" rule.

No Docker daemon in this sandbox (same constraint noted since tasks 0.2–0.4), so `.github/workflows/frontend.yml` — Postgres/Redis GitHub Actions services, PHP built-in server for the live API, pnpm for lint/typecheck/Vitest/Playwright — is written and passes locally against the equivalent native setup, but is unverified on a real GitHub Actions runner; please confirm it goes green on a PR, same caveat as `backend.yml` in task 0.4. `Makefile`'s `test-web`/`e2e`/`lint`/`fix`/`openapi` targets now shell into the `node` Compose service instead of the earlier placeholder echoes.

### Phase 0 exit criteria
- [x] Log in, create and switch companies, invite a member — in the browser.
- [x] RLS isolation and fail-closed tests pass; schema check enforced in CI.
- [x] CI green: CS, PHPStan max, Deptrac, PHPUnit, OpenAPI drift, frontend lint/types/tests/e2e.
- [ ] 🧑 Owner review of Phase 0 before starting Phase 1.

**Notes:** re-ran every gate locally (no Docker daemon in this sandbox, same constraint as tasks 0.2–0.4 and 0.13 — `backend.yml`/`frontend.yml` are unverified on a real GitHub Actions runner): PHP-CS-Fixer (0 fixes), PHPStan (0 errors), Deptrac (0 violations, 125 classes), full PHPUnit suite (86 tests/257 assertions, including `CompanyIsolationTest`'s three fail-closed/cross-tenant cases and `CompanyIsolationSchemaTest`'s "every company-scoped table has RLS enabled+forced+policied" check), `api/openapi.json` re-diffed clean against a fresh `nelmio:apidoc:dump`, and web `lint`/`typecheck`/`test`/`build`/`e2e` all green.

Browser walkthrough covered the full acceptance flow, not just the parts `onboarding.spec.ts` already automates: logged in as the seeded demo owner, created a company, switched between companies via the header dropdown, and invited a new member on the members screen (role select, table update) — all working end to end. This surfaced one sandbox-only false alarm worth recording: inviting a *new* (not-yet-registered) member sends an invitation email (`InviteUserToCompanyHandler::sendInvitationEmail`), and this sandbox has no Docker daemon and therefore no Mailpit container for `.env`'s `MAILER_DSN=smtp://127.0.0.1:1025` to reach — so a manual check via a bare `php -S` server 500'd on invite. Confirmed via `bin/console mailer:test` and `.env.test`'s own `MAILER_DSN=null://null` that this is purely the missing local SMTP sink, not application code: PHPUnit's `CompanyOnboardingFlowTest` already exercises this exact path against the null transport and passes, and re-running the manual check with a temporary (gitignored, not committed) `.env.local` override confirmed the invite succeeds end-to-end once a transport is reachable. In the real dev/CI environment (Docker Compose's `mailpit` service, or `frontend.yml`'s CI job which doesn't invite a *new* user in its e2e path) this doesn't occur. No code change was needed.

Everything technical is green; this phase is ready for 🧑 owner review before Phase 1 starts.

---

## Phase 1 — Master data (§6.2–6.5, §7.9.8, §7.10)

No longer blocked: exemption reasons and mainland VAT rates are resolved from
primary sources, and Açores/Madeira have an owner-supplied candidate value pending
final confirmation (`docs/legal/at-tabela-codigos-motivo-isencao.pdf`,
`docs/legal/civa-extracts.md`). See `docs/plans/phase-1.md` for the full task
breakdown and the decisions made while planning it (`docs/decisions/0003` among
them).

Full task breakdown, decisions and acceptance criteria: `docs/plans/phase-1.md`.

### 1.1 Global reference data: countries and units
- [x] `countries` (Shared, global) and `units` (Catalog, global) as versioned data migrations; `GET /api/v1/countries`, `GET /api/v1/units`.

**Accept:** migrations run; both endpoints return the seeded lists; PHPUnit covers the seed migration idempotency.

**Notes:** first entities in both Shared and Catalog, so this task also stood up the module scaffolding: added `Shared.Application`/`Shared.UIHttp` and all four `Catalog.*` Deptrac layers (Catalog didn't exist before), plus their `orm.mappings` entries in `config/packages/doctrine.yaml`. Hit a real Doctrine gotcha worth recording: `SimplifiedXmlDriver` (what `doctrine.orm.mappings` actually wires up, confirmed by reading the compiled container) uses `SymfonyFileLocator`, whose own doc comment says mapping files "consist of the short classname only" — it does **not** support subdirectories mirroring a sub-namespace the way plain `XmlDriver`/`DefaultFileLocator` does; its filename-join separator defaults to `.`, not `/`, so a nested `Reference/Country.orm.xml` silently fails to resolve (a generic "no mapping file found" error, reproduced and root-caused with a standalone PHP script against the actual vendor class before finding the fix). Fixed by keeping `Country` flat under `Shared\Domain` (`Country.php`, `Country.orm.xml` directly in each module's root), matching Platform's own existing convention — simpler than fighting the locator, and consistent with how `Role`/`Company`/`User` are already organised. `doctrine:migrations:diff` also proposed dropping `audit_log`/`idempotency_keys` as a false positive (they're raw-DBAL tables, not Doctrine entities, so the diff tool sees them as "extra"); stripped from the generated migration by hand. Seed migration inserts use `ON CONFLICT (code) DO NOTHING` so the idempotency test (`CountriesAndUnitsSeedMigrationTest`) can genuinely re-run the migration's queued SQL a second time and assert row counts don't change — migrations classes are deliberately excluded from Composer's autoloader (`config/packages/doctrine_migrations.yaml`'s own comment), so the test `require`s the file directly rather than importing it. Countries: full ISO 3166-1 alpha-2 list, Portuguese names (188 entries) — not legally sensitive per the plan, no `[VERIFY]`. Units: the starter set named in the plan (UN, KG, CX, L, M, M², M³, DZ, H) with reasonable per-unit decimal precision. Both endpoints require authentication (`IS_AUTHENTICATED_FULLY` already covers all of `/api/v1` by default) but have no company scope and no permission check, matching the plan's "any authenticated user may read global reference data" note. 101 tests total; PHPStan, Deptrac, CS-Fixer all green; `api/openapi.json` and the web client regenerated.

### 1.2 Tax reference data: tax rates and exemption reasons
- [x] `tax_rates`, `exemption_reasons` (new Tax module, global) as versioned data migrations; `GET /api/v1/tax-rates`, `GET /api/v1/exemption-reasons`; `Tax\Domain\TaxRateResolver`.

**Accept:** schema + migration mechanism merged and tested; mainland, Açores and Madeira tax rates and the full exemption-reasons table seeded; PT-AC/PT-MA rows flagged unconfirmed; `TaxRateResolver` errors instead of guessing when no rate matches.

**Notes:** first module built from scratch this phase (four new Deptrac layers, `orm.mappings` entry, a `tax_rate_id` typed-ID Doctrine type following `CompanyIdType`'s pattern, and the first-ever persisted `Percentage` — a new `PercentageType` (`NUMERIC(5,2)`) added to `Shared\Infrastructure` since `Percentage` itself lives in `Shared\Domain\Decimal`). `TaxRate`/`ExemptionReason` are plain immutable entities (no admin UI in this phase, decisions/0003) with an `isValidAt()` method covering both boundaries inclusively, unit-tested directly (`TaxRateTest`) rather than trusting the repository's DQL alone. `TaxRateResolver` throws `NoApplicableTaxRate` (a `ProblemDetails`) rather than silently picking the nearest rate, per the plan's explicit requirement — unit-tested with a mocked repository, since nothing calls it yet (Phase 2's `PriceCalculator` will).

Exemption reasons: transcribed the full M01–M99 table from `docs/legal/at-tabela-codigos-motivo-isencao.pdf` (V4.0) by rendering its pages to images and reading them directly (`pdfminer`'s text extraction badly scrambled the two-column table's reading order — installed `pymupdf` to render pages as PNGs instead, since `poppler-utils` couldn't be installed in this sandbox, no network access to the Ubuntu package mirror). `valid_from` per code follows the document's own change history, not one blanket date: the base table from "julho de 2022", M26/M34 from V2.0 (14-04-2023), M44/M45/M46 from V3.0 (14-08-2025), and M35 from its own stated application date (2026-07-01, more precise than the V4.0 publication date of 18-06-2026). Tax rates: mainland (6/13/23%) cited straight from `docs/legal/civa-extracts.md`'s CIVA art. 18.º text, `valid_from` 2011-01-01 (Lei n.º 55-A/2010's OE2011 effective date, cited in the article itself but not the exact day, so flagged as the law's well-known effective date rather than a verified exact one). Açores/Madeira use the owner-supplied candidate values, each row's own `description` stating "valor não confirmado" so the caveat travels with the data itself, not just this file — functional tests (`ExemptionReasonsControllerTest`, `TaxRatesControllerTest`) cover the `as_of`/`region` filters, including that `as_of` correctly excludes exemption codes not yet introduced on that date and mainland rates before 2011. A schema gotcha from task 1.1 recurred and was fixed the same way: `tax_rates` needed a real unique index (`region, code, valid_from`) added *before* the idempotent seed migration, since a fresh UUID `id` on every run meant `ON CONFLICT` had nothing to actually conflict on — caught by the idempotency test before it was ever committed. 116 tests total; PHPStan, Deptrac, CS-Fixer all green; `api/openapi.json` and the web client regenerated.

### 1.3 Document types
- [x] `document_types` (new Fiscal module, global) as a versioned data migration; `GET /api/v1/document-types`.

**Accept:** migration seeds exactly twelve rows with the flags matching §6.6; `RG` is the only non-signed type; a functional test covers the endpoint.

**Notes:** first Fiscal-module code (four new Deptrac layers, `orm.mappings` entry) — Fiscal itself won't consume this table until Phase 2's issuance flow, so this task is deliberately just the reference table plus an inspection endpoint. `signed` is `false` only for `RG` per §6.6's own inline comment ("receipts are not signed"), so every other type is `true` by direct textual implication — not a `[VERIFY]` item, since the scope already resolves it. `stock_effect`/`account_effect` are ordinary business-document modelling, not an AT format, so no legal citation is needed: `FR` (invoice-receipt) settles at issuance so carries no open receivable (`account_effect = none`), `ND` (debit note) adjusts a balance without moving goods, `OR`/`PF`/`NE` (quote/proforma/order) are pre-sale documents with neither effect yet. The two recurring `doctrine:migrations:diff` false positives from tasks 1.1/1.2 reappeared and were handled the same way: `audit_log`/`idempotency_keys` stripped by hand (permanent, expected — they're raw-DBAL tables, never Doctrine entities), and this time task 1.2's `tax_rates` unique index (hand-written SQL, never declared in its mapping XML) was also flagged for a `DROP INDEX` — fixed properly (not just stripped again) by adding a `<unique-constraints>` block to `TaxRate.orm.xml` declaring `uniq_tax_rates_region_code_valid_from`, verified by regenerating the diff and confirming the false positive disappeared. Seed migration uses `ON CONFLICT (code) DO NOTHING` with explicit `boolean` DBAL types for the two flag columns; the idempotency test re-runs the migration's queued SQL directly (same `require_once` pattern as tasks 1.1/1.2) and re-declares those types explicitly rather than relaying `Query::getTypes()`'s untyped return value, to keep PHPStan's inference sound. 120 tests total; PHPStan, Deptrac, CS-Fixer all green; `api/openapi.json` and the web client regenerated.

### 1.4 Company profile, fiscal settings and AT credentials
- [x] `company_profile`, `settings`, `at_credentials` (new Company module, company-scoped, RLS) per §6.2/§8.2; `GET/PUT /companies/{c}/profile`, `PUT /companies/{c}/at-credentials`, `POST /companies/{c}/at-credentials/test`; `CreateCompany` now also seeds a default profile.

**Accept:** profile/settings CRUD works; `at_credentials.password_encrypted` is verified ciphertext in a test (not the plaintext substring); isolation tests pass; CI schema check green for the three new tables.

**Notes:** first module other than Platform to need something Platform already had — a permission check and "who is the acting user" — which Deptrac's module boundaries don't let it reach directly (a module's `Application`/`UI\Http` layer may only depend on its own layers plus `Shared.Domain`, never another module's `Application`). Resolved with **docs/decisions/0004**: `PermissionChecker`, a new `CurrentActorId` (narrower than Platform's own `CurrentUserId` — returns a plain string, not the `UserId` value object, matching how `AuditLogger` already takes `?string $actingUserId`), `PermissionDenied` and `InvalidNif` all got Shared-level twins of Platform's existing ones, implemented by the same Platform Infrastructure adapters (one class, two interfaces) — Platform's own code is untouched, every later module (1.5 onward) uses the Shared ports directly. Also introduced the pattern for company-creation side effects other modules need: `CreateCompanyHandler` now dispatches a `CompanyRegistered` domain event on the `event.bus` scaffolded since task 0.5 but never used until now; since `event.bus` has no `doctrine_transaction` middleware of its own, a synchronous (untransported) dispatch runs the listener in the same already-open transaction and company context as the triggering command — no new plumbing needed. Company's own `CreateDefaultCompanyProfileOnCompanyRegistered` listens for it and inserts the default profile (pre-filled with the NIF/legal name the event already carries; everything else defaults to mainland Portugal, "normal" VAT regime, no cash-VAT).

Found and fixed a real, previously-latent bug while wiring this up: `query.bus` had no `doctrine_transaction` middleware, so a bare `EntityManager` read outside any transaction never triggers `CompanyContextConnection::beginTransaction()`, so `set_config('app.company_id', ...)` never runs, and an RLS-protected `SELECT` fails outright ("unrecognized configuration parameter app.company_id" — Postgres won't even evaluate `current_setting()` against a GUC nothing has ever `set_config`'d on that physical connection). This was invisible until now because every earlier query handler either read a global table or the two RLS-*exempt* tables (`memberships`, `api_tokens`, per `CompanyIsolationSchemaTest`'s own `EXEMPT_TABLES`) — `company_profile` is the first RLS-protected table anything has read via `query.bus`. Fixed by adding `doctrine_transaction` to `query.bus` too (a read-only transaction is still a transaction; changes nothing for queries that never needed one). Also fixed a pre-existing, harmless-but-wrong `services.yaml` alias (`Shared\Domain\Reference\CountryRepository`, a namespace that stopped matching the real interface once task 1.1 flattened `Country` — silently inert until this task became `CountryRepository`'s first real consumer, for validating `company_profile.country`).

`fiscal_region` is validated against `PT|PT-AC|PT-MA` in the entity itself (`InvalidFiscalRegion`); `vat_regime` is left a free-form non-empty string rather than a hardcoded enum, since neither the scope nor §14 fixes its valid values and nothing calculates against it yet — revisit once a real consumer (Phase 2+) needs specific values. AT subuser format (`<NIF>/<sequence number>`, e.g. `555555555/55`) is cited from `docs/legal/at-ws-efatura-aspetos-genericos.pdf` §2, not guessed, since it's an AT technical format (CLAUDE.md hard rule). `settings` got its schema, RLS and repository but no endpoint — nothing has a default to seed yet (`vat_regime`/`fiscal_region` etc. already live on `company_profile` directly), consumed starting with whichever later task needs its first key, matching how `signing_keys` has existed unused since task 0.7. `at_credentials` encryption is libsodium secretbox (XSalsa20-Poly1305, not a sealed box, since the app must decrypt again in Phase 3) with the key a dev-only committed default in `.env` (`AT_CREDENTIALS_ENCRYPTION_KEY`), same treatment as `APP_SECRET`. Isolation tests use repositories (not just raw SQL, unlike the Shared `CompanyIsolationTest`) and explicitly clear the EntityManager's identity map between company contexts — otherwise a cross-company `find()` would silently return the already-cached PHP object from the earlier write instead of proving anything about RLS. 150 tests total; PHPStan, Deptrac, CS-Fixer all green; `api/openapi.json` and the web client regenerated.

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
