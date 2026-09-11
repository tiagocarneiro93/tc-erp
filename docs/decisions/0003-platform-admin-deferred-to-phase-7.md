# 0003 — Platform admin (tax data, plans, company management) deferred to Phase 7

**Status:** Accepted
**Date:** 2026-09-11

## Context

While planning Phase 1 (master data), the owner raised whether `tax_rates`
and `exemption_reasons` (technical-scope.md §6.5, global reference data)
should be manageable through a "product admin" account — the same account
that would also manage subscription plans and create companies on a
customer's behalf.

No such account exists today. Every role in the system (`memberships.role`,
ADR 0002) is company-scoped, checked by `CompanyVoter` against a single
company's RLS-isolated data; the platform tables (§6.1) have no owner-side
UI at all. A "platform admin" would be a new authorization concept sitting
outside every company's RLS boundary — the first of its kind — and "manage
plans" implies a subscription/billing model that doesn't exist yet either.
technical-scope.md §12 already places billing/subscriptions in **Phase 7
(Pilot & launch)**, and §6.5 already documents tax rates and exemption
reasons as changing via **versioned data migrations**, not an admin UI
("changes (e.g. a new State Budget) ship as data migrations").

Three options were considered: (a) keep the current design — tax data via
migrations, admin/plans/company-management deferred to Phase 7 as already
scoped; (b) pull forward a minimal platform-admin concept in Phase 1, scoped
to just tax-rate/exemption-reason CRUD; (c) build the full back office
(admin auth, tax data CRUD, plan management, admin-driven company creation)
in Phase 1.

## Decision

Option (a). Phase 1 keeps §6.5's existing design: `tax_rates` and
`exemption_reasons` ship as versioned data migrations, owner-verified
against `docs/legal/` sources, exactly as already documented. No platform-
admin authorization concept, plan management, or admin-driven company
creation is built in Phase 1.

Platform admin, subscription plans, and admin-assisted company onboarding
stay in **Phase 7**, where they belong next to the billing/subscription
model they depend on — building the authorization concept before there is
a subscription model or a second real customer to justify it would be
speculative, and a cross-company authority is significant enough to design
once, deliberately, alongside plans rather than bolted on early for two
tables.

This does not block a fast-moving fix: if a tax rate or exemption reason
needs correcting between phases (e.g. a same-year State Budget change),
that's still a new migration, same as any other reference-data update —
no admin UI is needed to ship it.

## Consequences

- `docs/PLAN.md`'s Phase 1 scope is unchanged from what §6.5 already
  specified; no new tasks added for this.
- Phase 7's scope note in `docs/technical-scope.md` §12 now names this
  explicitly (platform admin: tax/exemption reference data management,
  subscription plans, admin-assisted company creation) so the idea isn't
  lost between now and then.
- When Phase 7 is planned, this ADR is the starting context: the
  cross-company authorization model, its relationship to `CompanyVoter`
  and RLS, and how it exposes `tax_rates`/`exemption_reasons` management
  are all still open design questions at that point.
