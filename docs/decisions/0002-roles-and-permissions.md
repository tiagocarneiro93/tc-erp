# 0002 — Roles and permissions

**Status:** Accepted
**Date:** 2026-09-11

## Context

Task 0.7 seeds `roles` and `role_permissions` (technical-scope.md §6.1). The
six roles are already named in `CLAUDE.md` (owner, admin, billing, stock,
accountant, read_only) and §8.1 names some of the actions they gate ("issue
documents, cancel, manage series, stock, purchases, settings, read-only,
accountant"), but no concrete permission list exists yet. This ADR proposes
one, seeded now so `memberships.role` has something real to reference, and
refined as each phase adds the features a permission actually gates (e.g.
`documents.issue` is unused until Phase 2's issuance use case exists).

## Decision

Permissions are `area.action` strings, checked later by a `CompanyVoter`
(task 0.9) against the caller's membership role.

| Permission | Meaning |
|---|---|
| `company.manage` | Edit company profile, fiscal settings, AT credentials |
| `company.delete` | Close/delete the company account — owner only |
| `members.manage` | Invite/remove members, change their role |
| `series.manage` | Create, communicate, finish document series |
| `documents.issue` | Issue fiscal documents (invoices, credit notes, receipts, transport docs) |
| `documents.cancel` | Cancel an issued document |
| `documents.read` | View/list/download issued documents |
| `customers.manage` | Create/edit customers and suppliers |
| `customers.read` | View customers and suppliers |
| `products.manage` | Create/edit products, families, price lists |
| `products.read` | View products, families, price lists |
| `stock.manage` | Adjustments, transfers, counts |
| `stock.read` | View stock levels and movements |
| `purchases.manage` | Supplier documents and payments |
| `purchases.read` | View supplier documents and payments |
| `accounts.read` | View current accounts (customer/supplier balances, statements) |
| `reports.read` | Dashboard, sales/VAT/stock reports, SAF-T export |

Role → permission grants:

| Role | Permissions |
|---|---|
| `owner` | everything, including `company.delete` |
| `admin` | everything except `company.delete` |
| `billing` | `documents.issue`, `documents.cancel`, `documents.read`, `series.manage`, `customers.manage`, `customers.read`, `accounts.read`, `reports.read` |
| `stock` | `products.manage`, `products.read`, `stock.manage`, `stock.read`, `purchases.manage`, `purchases.read` |
| `accountant` | `documents.read`, `customers.read`, `products.read`, `stock.read`, `purchases.read`, `accounts.read`, `reports.read` |
| `read_only` | every `*.read` permission, nothing else |

`accountant` and `read_only` end up with the same permission set in this
first seed — the distinction is organisational (an accountant is the role
label for someone who typically holds it across several companies at once,
technical-scope.md §3.1) rather than a different in-company grant. They can
diverge later (e.g. if accountants get a `reports.export` distinct from
`reports.read`) without changing the seed's shape.

Seeded in the migration that creates `roles`/`role_permissions` (task 0.7);
never edited in place once committed (CLAUDE.md — never edit a committed
migration) — a permission list change ships as a new migration.

## Consequences

- Several permissions (`documents.issue`, `series.manage`, …) have no
  enforcement point yet — they exist so the seed data is complete and
  stable, and get real teeth when task 0.9's `CompanyVoter` and each
  phase's actual endpoints land.
- Adding a permission later (e.g. a `saft.export` distinct from
  `reports.read`) is an additive migration inserting new
  `role_permissions` rows, not a rename of this list.
