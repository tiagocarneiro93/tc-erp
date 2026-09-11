# 0004 — Cross-module security ports live in Shared.Domain; company creation side-effects use a domain event

**Status:** Accepted
**Date:** 2026-09-11

## Context

Task 1.4 is the first task to add a module (Company) other than Platform that
needs two things every future module will also need:

1. To check a permission (`company.manage`) the same way Platform's own
   command handlers already do, via `PermissionChecker::isGranted()`.
2. To know which user is making the request, in a controller, to build an
   outgoing command's `actingUserId` field for audit logging.

Both ports (`PermissionChecker`, `CurrentUserId`) currently live in
`App\Platform\Application\Security`. Deptrac's ruleset (`api/deptrac.yaml`)
only lets a module's `Application`/`UI\Http` layer depend on its own layers
plus `Shared.Domain` — never another module's `Application` layer. Company
(and Parties, Catalog, Inventory, … in later tasks — ADR 0002 already names
permissions like `customers.manage`, `products.manage`, `stock.manage` for
modules that don't exist yet) cannot import a Platform-namespaced interface
without violating that rule.

Separately, `CreateCompanyHandler` (task 0.10) explicitly deferred seeding
per-module company defaults: "No default settings/warehouse/series here —
those modules (Inventory, Fiscal) do not exist yet in Phase 0." Task 1.4 is
the first to actually need one (a default `company_profile` row), and every
later module that needs a company-creation side effect (a default warehouse
in Inventory, a default series in Fiscal) will hit the same problem:
Platform's `CreateCompanyHandler` cannot depend on Company's/Inventory's/
Fiscal's `Application` layer any more than the reverse is allowed.

## Decision

**Security ports:** add `App\Shared\Domain\Security\PermissionChecker` and
`App\Shared\Domain\Security\CurrentActorId` — new interfaces in Shared.Domain,
structurally identical in spirit to the existing `Clock`, `CompanyContext`
and `AuditLogger` ports (a cross-cutting concern every module needs, defined
once in Shared, implemented wherever the concrete knowledge lives).

- `PermissionChecker::isGranted(string $permission, CompanyId $companyId): bool`
  is byte-for-byte the same signature as Platform's existing one (it already
  used only `string` and `Shared\Domain\CompanyId`, no Platform types) — so
  `SymfonyPermissionChecker` now implements *both* interfaces rather than
  Platform's being renamed, keeping every existing Platform call site
  untouched.
- `CurrentActorId::id(): string` is a new, narrower sibling to Platform's own
  `CurrentUserId::id(): UserId` (which stays exactly as it is, for Platform's
  own use) — modules outside Platform only need the id as an opaque string to
  put on a command (exactly how `AuditLogger::log()` already accepts
  `?string $actingUserId`, not a typed id), so the new port returns `string`
  instead of introducing a dependency on Platform's `UserId` value object.
  `App\Platform\Infrastructure\Security\SymfonyCurrentActorId` implements it,
  reusing the same `SecurityUser` unwrapping as `SymfonyCurrentUserId`.
- `App\Shared\Domain\Exception\PermissionDenied` (implements `ProblemDetails`,
  403) is the Shared-level twin of Platform's own
  `Platform\Domain\Exception\PermissionDenied`, for the same reason: a
  Company (or later, Parties/Catalog/…) handler cannot throw a
  `Platform\Domain\Exception` any more than it can import a
  `Platform\Application` interface. New modules throw the Shared one.
- Same reasoning, same shape: `App\Shared\Domain\Exception\InvalidNif`
  (422) is the cross-module twin of `Platform\Domain\Exception\InvalidNif`,
  thrown wherever `Nif::fromString()` is validated outside Platform (task
  1.4's `UpdateCompanyProfile`, and 1.5's customers/suppliers next).
- `App\Shared\Domain\Exception\InvalidCountryCode` started life as
  `Company\Domain\Exception\InvalidCountryCode` in task 1.4 (validating
  `company_profile.country` against the `countries` table) and was moved to
  Shared as soon as task 1.5 needed the identical check for
  `customers`/`suppliers.country` — unlike `PermissionDenied`/`InvalidNif`
  above, this one had no other Platform usage to preserve, so it was
  promoted in place rather than duplicated.

Both new ports are aliased in `config/services.yaml` to the same Platform
Infrastructure classes as before (`SymfonyPermissionChecker`,
`SymfonyCurrentActorId`) — one concrete implementation, two interfaces,
because only Platform's `Infrastructure` layer has access to
`AuthorizationCheckerInterface`/`SecurityUser`.

**Company-creation side effects:** add `App\Shared\Domain\Event\CompanyRegistered`
(company id, NIF, legal name, occurred-at — plain data, no framework
dependency) and dispatch it from `CreateCompanyHandler` on the existing
`event.bus` (scaffolded in task 0.5, unused until now) right after the
company/membership/audit-entry inserts. `event.bus` has no
`doctrine_transaction` middleware of its own, so a synchronous (untransported)
dispatch runs its handlers in the same PHP call frame and therefore the same
already-open transaction and company context as the `CreateCompany` command
that triggered it — no new transactional plumbing needed.

Company's own `Company\Application\Event\CreateDefaultCompanyProfileOnCompanyRegistered`
(`#[AsMessageHandler(bus: 'event.bus')]`) listens for it and inserts the
default `company_profile` row, pre-filled with the NIF/legal name the event
already carries. Any future module needing a company-creation side effect
(Inventory's default warehouse, Fiscal's default series) subscribes to the
same event instead of Platform depending on that module directly.

## Consequences

- Platform's existing `Platform\Application\Security\{PermissionChecker,CurrentUserId}`
  and `Platform\Domain\Exception\PermissionDenied` are untouched — zero risk
  to already-shipped Phase 0/1 code, only additive files plus two new
  `implements` clauses on `SymfonyPermissionChecker`.
- Every module from here on (Parties in 1.5, Catalog in 1.6/1.7, Inventory in
  1.9, …) checks permissions via `Shared\Domain\Security\PermissionChecker`
  and reads the acting user via `Shared\Domain\Security\CurrentActorId`
  directly — no further ADR needed to repeat this pattern.
- `event.bus` now has its first real handler; `CompanyRegistered` is the
  template for any future "something happened during company creation that
  another module cares about" need — a new module subscribes, it never
  requires touching `CreateCompanyHandler` again.
