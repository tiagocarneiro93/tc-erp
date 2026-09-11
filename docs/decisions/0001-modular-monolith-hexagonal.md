# 0001 — Modular monolith with hexagonal modules

**Status:** Accepted
**Date:** 2026-09-11

## Context

`docs/technical-scope.md` §4 and `CLAUDE.md` already settle the high-level
shape: a modular monolith (one deployable, many bounded-context modules)
where each module is internally layered hexagonally (ports & adapters).
Task 0.5 is where that becomes real code and a CI-enforced rule rather than
prose. Two things needed a concrete answer that the scope doesn't spell out:
how to lay out the module skeleton on disk, and how to make Deptrac actually
enforce "modules communicate through application services or domain events,
never through another module's repositories" — not just the four generic
layering rules — given Deptrac has no way to parametrise a layer by a regex
capture group (i.e. no built-in notion of "this module's Domain" as opposed
to "that module's Domain").

## Decision

### Module skeleton

Every module is a directory under `api/src/<Module>/` with up to four
sub-directories:

```
src/<Module>/
  Domain/          entities, value objects, domain services, domain events, ports
  Application/     commands, queries, handlers (use cases), DTOs
  Infrastructure/  Doctrine repositories, XML mapping, adapters for external services
  UI/Http/         controllers, request/response DTOs, OpenAPI attributes
```

`Domain` never imports Symfony, Doctrine, or any other framework code; ORM
mapping for its entities lives as XML under
`Infrastructure/Persistence/Doctrine/Mapping`, registered as one named
Doctrine mapping per module in `config/packages/doctrine.yaml` (Doctrine
mapping config can't glob `src/*/...` the way a Deptrac collector regex
can — it needs one explicit `dir`/`prefix` pair per module, added when that
module gets its first entity).

`Shared` is the one exception: it has `Domain/` and `Infrastructure/` (the
`Clock` interface and its system implementation, value objects, IDs — task
0.6) but no `Application/` or `UI/Http/`, since it exposes no use cases or
endpoints of its own.

This task creates `Shared` and `Platform`. Every later phase adds its
module(s) the same way (Parties, Catalog, Tax in Phase 1; Fiscal, Accounts,
Inventory in Phase 2; and so on per `docs/PLAN.md` §12).

### Deptrac: one layer per (module, tier)

Deptrac layers are a fixed, named set — there is no "for each module,
generate a Domain layer" construct. A single generic `Domain` layer
matching `App\*\Domain\*` across every module (what task 0.4 shipped as a
placeholder) can enforce the four generic layering rules, but it is
useless for module isolation: two different modules' Domain classes both
land in the same "Domain" layer, so Deptrac cannot tell a legitimate
same-module dependency from a forbidden cross-module one.

The fix is to give every module its own four named layers
(`<Module>.Domain`, `<Module>.Application`, `<Module>.Infrastructure`,
`<Module>.UIHttp`) and a ruleset entry for each, following this pattern
(see `api/deptrac.yaml`, `Platform.*`):

| Layer | May depend on |
|---|---|
| `<Module>.Domain` | itself, `Shared.Domain` |
| `<Module>.Application` | itself, `<Module>.Domain`, `Shared.Domain`, **any module's** `Application` layer |
| `<Module>.Infrastructure` | itself, `<Module>.Application`, `<Module>.Domain`, `Shared.Domain`, `Shared.Infrastructure` |
| `<Module>.UIHttp` | itself, `<Module>.Application`, `<Module>.Domain`, `Shared.Domain` |

The `Application` row is the one that encodes "modules talk through
application services": any module's `Application` may call any other
module's `Application`, but nothing may call another module's `Domain`,
`Infrastructure` or `UI/Http` directly. Adding a new module means copying
this four-row block with the module's name substituted — mechanical, but
explicit and reviewable, which is preferable to Deptrac silently not
checking cross-module isolation at all.

### Messenger buses

Three buses (`config/packages/messenger.yaml`):

- `command.bus` — synchronous, `doctrine_transaction` middleware, so every
  command handler runs inside a transaction against the `default`
  (`app_runtime`) connection. This is where company-scoped work eventually
  gets its `SET LOCAL app.company_id` middleware (task 0.9).
- `query.bus` — synchronous, no transaction middleware (reads don't need
  one at this layer; read-side transactional needs are handled per query
  if they ever arise).
- `event.bus` — synchronous for now, `allow_no_handlers: true` since most
  domain events won't have a subscriber yet.

A `async` transport is configured on Redis but nothing is routed to it yet
— it exists for Phase 3's AT-communication outbox, per `docs/PLAN.md`.

### Proof of wiring

`Platform\Application\Command\Ping` / `PingHandler` is a smoke-test command:
its handler reports whether a DBAL transaction is active, proving
`doctrine_transaction` middleware actually wraps `command.bus` dispatches.
It reads `Doctrine\DBAL\Connection` directly rather than through a
dedicated Domain port, which strict modules must not do — but `CLAUDE.md`
explicitly allows this pragmatism outside Fiscal/Tax/Inventory/Accounts,
and a single-purpose port for a smoke test would be exactly the kind of
speculative interface CLAUDE.md tells us not to build.

## Consequences

- New modules must add their own Deptrac layer block and, once they have
  entities, a Doctrine mapping entry. Both are mechanical but not
  automatic — forgetting them means Deptrac silently under-enforces
  isolation for that module rather than failing loudly. Worth revisiting
  if Deptrac ever gains templated/parametrised layers.
- Cross-module calls happen through `Application` services only; nothing
  stops one module's `Application` handler from injecting another's
  application service directly (rather than via a port), which is looser
  than pure hexagonal architecture but matches what `docs/technical-scope.md`
  actually asks for.
