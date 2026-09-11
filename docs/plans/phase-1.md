# Phase 1 — Master data

Scope per `docs/PLAN.md`: §6.2–6.5, §7.9.8, §7.10 of `docs/technical-scope.md`.

**Prerequisite (blocking, Açores/Madeira tax rates only):** exemption reasons are
resolved (`docs/legal/at-tabela-codigos-motivo-isencao.pdf`, V4.0), and mainland VAT
rates are resolved (`docs/legal/civa-extracts.md`, CIVA art. 18.º n.º 1: 6%/13%/23%,
pasted by the owner since this sandbox's network egress is blocked for
`portaldasfinancas.gov.pt`). **Açores and Madeira's actual rates are still open**:
art. 18.º §3 only says the regions *may* set their own reduced rates under Lei
Orgânica n.º 2/2013 — it doesn't state the current values, and the widely-quoted
16/9/4 and 22/12/5 figures found by web search are secondary sources, not the
regional decree itself. 🧑 owner needs to supply that decree (or confirm the
figures against it) before task 1.2 can seed PT-AC/PT-MA for real. Everything else
in this phase has no external dependency.

Depends on Phase 0 being complete: module/Deptrac skeleton (0.5), `Clock`/`Nif`/decimal
value objects (0.6), auth (0.8), `CompanyContext` + RLS + isolation test pattern (0.9),
`CreateCompany` use case (0.10), Problem Details/pagination/OpenAPI conventions (0.11),
web shell with company switcher (0.13). Tasks below assume all of that exists; do not
start 1.1 until Phase 0's exit criteria are met.

Every new company-scoped write endpoint is permission-gated the same way task
0.10's `InviteUserToCompanyHandler` checks `members.manage` — via
`PermissionChecker::isGranted()` against `docs/decisions/0002-roles-and-permissions.md`'s
existing list (`company.manage` for 1.4, `customers.manage`/`customers.read` for 1.5,
`products.manage`/`products.read` for 1.6–1.8, `stock.manage`/`stock.read` for
warehouses in 1.9 — there's no dedicated warehouse permission in ADR 0002, and a
warehouse is a stock-location concept). The global read-only reference-data endpoints
(1.1, 1.3, and `tax_rates`/`exemption_reasons` in 1.2) need no permission check —
any authenticated user may read them, same as they're global, not company-scoped.

## Decisions this plan makes (owner: confirm or override before coding)

1. **Module placement** for reference data not explicitly assigned in §4.1: `units` →
   *Catalog* (only Catalog consumes it), `countries` → *Shared* (consumed by both
   *Parties* and *Company*). Flagging since the scope table doesn't say.
2. **New endpoint not in §9.3:** `POST /companies/{c}/products/{id}/prices/calculate`
   — the "live display of the other value" conversion mentioned in `docs/PLAN.md`'s
   Phase 1 scope needs a home. Per CLAUDE.md rule 6, proposing it here rather than
   adding it silently. It calls a small pure function in `Tax\Domain`
   (`VatConversion` — net↔gross at 6 decimals for one line, no discounts, no
   multi-line totals) that becomes the first building block of the real
   `PriceCalculator` in Phase 2, so Phase 2 extends it rather than replacing it
   (§7.9.2 principle 1: one calculator, no second implementation).
3. **Tax seed data:** mainland rates (6 %/13 %/23 %, codes RED/INT/NOR) are now
   resolved from the primary source — `docs/legal/civa-extracts.md`, CIVA art. 18.º
   n.º 1 — so task 1.2 seeds them for real, not as a placeholder. `exemption_reasons`
   is likewise seeded in full from `docs/legal/at-tabela-codigos-motivo-isencao.pdf`
   (V4.0). **PT-AC/PT-MA rates stay empty** until the owner supplies the actual
   regional decree (see prerequisite above) — confirm this split is acceptable.
4. Extends the Phase 0 `CreateCompany` use case (additive, not a fiscal table) to
   also create the default warehouse (task 1.9) — `warehouses` doesn't exist until
   this phase.
5. **No platform-admin UI for `tax_rates`/`exemption_reasons` in this phase**
   (decisions/0003, agreed while planning this phase): they ship as versioned data
   migrations per §6.5, exactly as already documented. A cross-company "platform
   admin" account (managing this reference data, subscription plans, and
   admin-assisted company creation) is deferred to Phase 7, next to the
   billing/subscription model it depends on.

---

### 1.1 Global reference data: countries and units
- `countries` (Shared, global): ISO 3166-1 alpha-2 code, name. Seed the full list —
  not legally sensitive, no [VERIFY].
- `units` (Catalog, global): code PK, name, decimals. Seed a standard starter set
  (UN, KG, CX, L, M, M2, M3, DZ, H).
- Both as versioned data migrations (never edit a committed one — new migration for
  any later addition).
- `GET /api/v1/countries`, `GET /api/v1/units` (read-only, no company scope).

**Accept:** migrations run; both endpoints return the seeded lists; PHPUnit covers
the seed migration idempotency (running twice doesn't duplicate rows).

### 1.2 Tax reference data: tax rates and exemption reasons
- `tax_rates`, `exemption_reasons` (Tax module, global) per §6.5, XML mapping,
  migrations.
- Versioned seed mechanism: each rate/reason change ships as a new data migration
  (e.g. named after the State Budget year), never an edit to an old one — mirrors
  the "never edit a committed migration" rule for the fiscal-adjacent nature of
  this data. No admin UI edits this data in this phase (decision 5 above).
- `exemption_reasons`: seed the **full real M01–M99 list** from
  `docs/legal/at-tabela-codigos-motivo-isencao.pdf` (V4.0, 18 Jun 2026) — code,
  invoice wording (`description`), and legal basis (`legal_reference`) transcribed
  exactly as the table states, citing the document in the migration's own comment
  per CLAUDE.md's "[VERIFY] must cite document and section" rule.
- `tax_rates`: seed **mainland rates** (RED 6%, INT 13%, NOR 23%) from
  `docs/legal/civa-extracts.md`, CIVA art. 18.º n.º 1 (decision 3 above); leave
  PT-AC/PT-MA empty until the owner supplies the actual regional decree, with a
  `TODO` migration stub the owner fills in.
- `GET /api/v1/tax-rates`, `GET /api/v1/exemption-reasons`, both filterable by
  `region` and resolvable "as of" a given date.
- Domain service `Tax\Domain\TaxRateResolver`: given `(region, code, date)` →
  applicable rate, erroring (not silently picking the nearest) if none matches —
  this is what Phase 2's `PriceCalculator` will call.

**Accept:** schema + migration mechanism merged and tested; mainland tax rates and
the full exemption-reasons table seeded and covered by tests — a validity-date
resolution unit test for `tax_rates` (rate changes on `valid_from`/`valid_to`
boundaries) and a test asserting the exemption-reasons seed matches the source
document's row count and a sample of codes; PT-AC/PT-MA rates explicitly pending,
tracked as a follow-up note in this file until the owner supplies the regional
decree, at which point they ship as a small additive migration + 🧑 owner review
(per CLAUDE.md: "pricing test vectors reviewed by the owner" applies here too,
since this data feeds VAT calculation).

### 1.3 Document types
- `document_types` (Fiscal module, global) per §6.6: the twelve v1 types (FT, FS,
  FR, NC, ND, RG, GT, GR, GD, OR, PF, NE) with `saft_section`, `signed`,
  `stock_effect`, `account_effect`, `requires_at_prior_communication`, seeded via
  migration — structural data, not legally variable, no [VERIFY] block.
- `GET /api/v1/document-types` (read-only, inspection/debug value; Fiscal itself
  reads this table directly in later phases, no dependency on the endpoint).

**Accept:** migration seeds exactly twelve rows with the flags matching §6.6;
unit test asserts the full set and its flags.

### 1.4 Company profile, fiscal settings and AT credentials
- `company_profile`, `settings` (Company module, company-scoped, RLS) per §6.2.
- `at_credentials` (subuser, password_encrypted, validated_at, last_error),
  encrypted at rest with libsodium per §8.2 (key from a dev-only env secret,
  git-ignored, same treatment as the Phase 2 signing key). No AT calls yet — the
  "test" endpoint only round-trips encrypt/decrypt and checks format, it does not
  reach the AT (that's Phase 3).
- Extend `CreateCompany` (Phase 0) to also insert a default `company_profile` row
  and empty `settings`.
- `GET/PUT /companies/{c}/profile`, `PUT /companies/{c}/at-credentials`,
  `POST /companies/{c}/at-credentials/test` (format/round-trip only, documented
  as a stub until Phase 3).
- Isolation tests for all three tables.

**Accept:** profile/settings CRUD works; `at_credentials.password_encrypted` is
verified ciphertext in a test (not the plaintext substring); isolation tests
pass; CI schema check green for the three new tables.

### 1.5 Customers and suppliers
- `customers`, `suppliers`, `addresses` (Parties module, company-scoped) per §6.3,
  reusing the `Nif` value object from Phase 0 (0.6).
- Seed the generic "Consumidor final" customer (NIF 999999990) as part of
  `CreateCompany`. **[VERIFY]** noted in scope: rules/limits for invoices without
  a customer NIF are not enforced yet (that's a Phase 2 issuance concern) — this
  task only seeds the record.
- Foreign customers: free-text country VAT ID field, using the `countries` table
  from 1.1 for the country selector; no per-country ID format validation in v1.
- CRUD + cursor-paginated list (filter/search by code, NIF, name) for both.
- Isolation tests.

**Accept:** CRUD in API; "Consumidor final" present after `CreateCompany`; NIF
validation rejects invalid check digits (reuses existing `Nif` tests); isolation
tests pass; OpenAPI regenerated.

### 1.6 Products and product families
- `products` (kind `simple` only for now — `kit` structurally allowed but
  composition comes in 1.8), `product_families` (tree via `parent_id`,
  cycle-free) per §6.4, company-scoped.
- `last_cost`/`average_cost` columns present but nullable/unused until Phase 5
  (Inventory) populates them.
- CRUD + cursor-paginated list (filter by family, active, track_stock, search by
  code/description/barcode).
- Isolation tests.

**Accept:** CRUD in API; family tree rejects a cycle on write; isolation tests
pass; OpenAPI regenerated.

### 1.7 Price lists and product prices (§7.9.8)
- `price_lists` (id, name, default_includes_vat), `product_prices` (product_id,
  price_list_id, amount stored exactly as entered, includes_vat), company-scoped,
  `PRIMARY KEY (company_id, product_id, price_list_id)`.
- The system never overwrites the entered value with a converted one — enforced
  by only ever storing what the request body sent.
- `POST /companies/{c}/products/{id}/prices/calculate` (decision 2 above): given
  an amount + `includes_vat`, returns the other value using
  `Tax\Domain\VatConversion` (from 1.2's `TaxRateResolver`) resolved against the
  product's tax rate — display-only, never persisted by this endpoint.
- Isolation tests.

**Accept:** prices round-trip exactly as entered (no silent rounding beyond the
stored precision); conversion endpoint matches the worked example in
`technical-scope.md` §7.9.8 (9,99 / 1,23 = 8,121951) in a unit test; isolation
tests pass; OpenAPI regenerated.

### 1.8 Kit composition (§7.10)
- `product_components` (kit_product_id, component_product_id, quantity,
  sort_order), company-scoped.
- **No nesting**: reject adding a component whose own `kind = 'kit'` (§7.10.1
  decision: not v1, not just cycle-checked — nesting is disallowed outright).
- Informational-only VAT-rate mismatch warning: a computed field on the kit's
  read model when a component's tax rate differs from the kit's own, never a
  write-time error (§7.10.2: "the decision stays with the user").
- CRUD endpoint for a kit's component list; kit cost preview
  (Σ component `average_cost` × quantity) — will read as zero/null until Phase 5
  populates `average_cost`, documented as a known gap, not a bug.
- Isolation tests.

**Accept:** adding a kit-typed product as a component is rejected with a stable
`problem+json` type code; VAT-mismatch warning surfaces in the API response
without blocking the write; isolation tests pass.

### 1.9 Warehouses
- `warehouses` (id, code, name, address, is_default, active), company-scoped.
- Extend `CreateCompany` to also insert one default warehouse (decision 4).
- CRUD + list.
- Isolation tests.

**Accept:** new company gets exactly one default warehouse; isolation tests pass;
OpenAPI regenerated.

### 1.10 Web: master data screens
- TanStack Table list screens (cursor pagination, filters, search) + React Hook
  Form + Zod forms for: customers, suppliers, products (with the kit-composition
  sub-editor and the net/gross price preview from 1.7), product families, price
  lists, warehouses.
- Company profile / fiscal settings / AT credentials screens (1.4).
- Read-only browsers for reference data: countries, units, tax rates, exemption
  reasons, document types.
- pt-PT number/date formatting via Phase 0 utilities throughout.

**Accept:** Playwright e2e — create a customer, create a product with a
price (verify the live net/gross preview), build a kit from two existing
products, create a warehouse, switch company and confirm the lists change.

---

## Phase 1 exit criteria

- Full CRUD via API and web for customers, suppliers, products, product
  families, price lists, warehouses.
- Reference data (countries, units, tax rates, exemption reasons, document
  types) browsable read-only in API and web.
- Isolation test for every new company-scoped table; CI schema check green.
- `make openapi` run; `api/openapi.json` and the regenerated web client
  committed; OpenAPI drift check green in CI.
- `make lint` and `make test` green (per CLAUDE.md, on every task, not just at
  the end).
- Playwright e2e from task 1.10 passes in CI.
- 🧑 Owner confirms mainland tax-rate seed data and the exemption-reasons seed
  (task 1.2) are correct, and supplies the Açores/Madeira regional decree so
  PT-AC/PT-MA rates can be completed before Phase 2 needs them (Phase 2's own
  prerequisite already requires this).
- 🧑 Owner review of Phase 1 before starting Phase 2.
