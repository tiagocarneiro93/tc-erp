# Invoicing & ERP Platform — Technical Scope v0.7

**Codename:** tc-erp (product brand to be defined)
**Author:** Tiago Carneiro (TCWeb)
**Status:** Draft for discussion
**Changes in v0.2:** tenancy changed to a single shared database with Row-Level Security (§5); new pricing, VAT and rounding design (§7.9).
**Changes in v0.3:** discount order (§7.9.4), product prices with or without VAT (§7.9.8), composite products / kits (§7.10) — kits priced and taxed as a single item.
**Changes in v0.4:** working documents (quotes, proformas, orders) moved into v1 (§6.8); open decisions reorganised by phase with recommendations (§14).
**Changes in v0.5:** Phase 0 decisions confirmed (§14.1).
**Changes in v0.6:** PDFs generated on demand, only sealed PDFs sent electronically are stored (§7.8); issuer snapshot and template version on documents; print log.
**Changes in v0.7:** seal model (per-company qualified seal, remote hash signing, PAdES embedded in PHP) and storage sizing (§7.8).
**Date:** September 2026

> This is a living document. Every section marked **[DECIDE]** is an open decision to discuss before implementation. Items marked **[VERIFY]** must be confirmed against the current legislation (Portaria 363/2010, Despacho 8632/2014, Decreto-Lei 28/2019, the ATCUD/QR code Portaria, and the SAF-T (PT) technical notes and XSD on the Portal das Finanças) before being implemented.

---

## 1. Context and goals

A multi-tenant SaaS invoicing and ERP platform for Portuguese SMEs, certified by the Autoridade Tributária (AT), built API-first so that the web app, third-party integrations and future AI integrations all consume the same API.

**Goals for v1 (first certifiable version)**

- Issue all core fiscally relevant documents in compliance with AT certification requirements.
- Customers, suppliers, products and services, taxes.
- Stock management and purchases.
- SAF-T (PT) export and real-time communication with the AT via webservices (series, invoices, transport documents).
- Qualified electronic seal on electronically sent invoices (mandatory from 1 January 2027 — since v1 will launch after that date, this is a **launch requirement**, not a later phase).

**Non-goals for v1**

- POS / touch interface.
- Accounting (SAF-T accounting, journal entries).
- CIUS-PT structured e-invoicing for B2G (planned right after v1, see §12).
- MCP / AI integration (the API is designed to support it later).

## 2. Key decisions (taken)

| Area | Decision | Rationale |
|---|---|---|
| Backend | PHP 8.3+ / Symfony 7.x | Strong existing expertise; explicit DI and Doctrine data mapper suit a strict domain core |
| Frontend | React + TypeScript (Vite SPA) | Component model solves the jQuery/views integration pain; largest ecosystem |
| Database | PostgreSQL 16+ | Transactional DDL, rich constraints/triggers, strict NUMERIC, JSONB |
| Tenancy | Multi-tenant SaaS, **single shared database**, isolation via PostgreSQL Row-Level Security | Far simpler to operate solo; RLS enforces isolation in the database (§5) |
| Money | Decimal arithmetic only (`brick/math`, `NUMERIC`), one price calculator, explicit net/gross pricing mode | Eliminates cent differences between shop, UI and invoice (§7.9) |
| Architecture | Modular monolith, hexagonal (ports & adapters) per module | SOLID where it matters, single deployable for a solo developer |
| API | REST + JSON, OpenAPI as the contract | Web app, integrations and future MCP all consume the same API |
| Repository | Monorepo: `/api`, `/web` | One CI pipeline, contract changes visible everywhere |

## 3. Requirements

### 3.1 Functional (v1)

- **Platform:** sign-up, users, companies, memberships and roles (a user can access several companies — essential for accountants), company switcher.
- **Company settings:** company data, fiscal settings, AT webservice credentials, document series, users and permissions.
- **Master data:** customers, suppliers, products/services, units, families, price lists, tax rates, exemption reasons, warehouses.
- **Sales documents:** Fatura (FT), Fatura simplificada (FS), Fatura-recibo (FR), Nota de crédito (NC), Nota de débito (ND).
- **Working documents:** Orçamento (OR), Fatura pró-forma (PF), Nota de encomenda (NE), with conversion into sales and movement documents (§6.8).
- **Receipts:** Recibo (RG), settlement of open invoices.
- **Movement of goods:** Guia de transporte (GT), Guia de remessa (GR), Guia de devolução (GD), with prior communication to the AT.
- **Current accounts:** customer and supplier balances, open items, statements.
- **Purchases:** supplier invoices, credit notes, goods receipts, supplier payments.
- **Stock:** warehouses, stock movements, stock levels, weighted average cost, adjustments, stock counts.
- **Compliance:** SAF-T (PT) export, AT webservices, PDF with QR code and certification mentions, qualified seal, training mode, audit trail.
- **Output:** PDF generation, email sending, document archive.
- **Reporting:** dashboard (sales, VAT, open balances, stock), basic listings and exports.

### 3.2 Non-functional

- **Integrity above all:** no code path may alter fiscal data after issuance; enforced in the database, not only in code.
- **Correctness under concurrency:** gapless, chronological numbering per series with simultaneous users and API clients.
- **Price consistency:** the total on the invoice always equals the total shown by the UI or the integrated shop, to the cent (§7.9).
- **Idempotency:** a retried issue request must never create two documents.
- **Availability:** document issuance must not depend on the AT being available (except transport documents, see §7.3).
- **Retention:** fiscal documents archived for 10 years, readable and exportable.
- **Security:** company isolation (RLS), encrypted secrets (AT credentials, signing key), access control to Despacho standards (§8).
- **Performance targets (initial):** issuance < 500 ms p95 excluding PDF; lists < 300 ms p95 with pagination.
- **Scale assumption:** thousands of SME companies on one PostgreSQL database before revisiting (§10.4).

---

## 4. Architecture overview

```
                    ┌───────────────────────┐
                    │  Web app (React SPA)  │     3rd-party integrations
                    └───────────┬───────────┘     (API tokens, later OAuth2)
                                │ HTTPS / JSON              │
                    ┌───────────▼───────────────────────────▼──┐
                    │        Symfony API (modular monolith)    │
                    │  ┌─────────┐ ┌────────┐ ┌─────────────┐  │
                    │  │Platform │ │ Fiscal │ │  Inventory  │  │
                    │  ├─────────┤ ├────────┤ ├─────────────┤  │
                    │  │ Parties │ │Catalog │ │  Purchases  │  │
                    │  ├─────────┤ ├────────┤ ├─────────────┤  │
                    │  │Accounts │ │  Tax   │ │AtIntegration│  │
                    │  └─────────┘ └────────┘ └─────────────┘  │
                    └──┬──────────────┬───────────────┬────────┘
                       │              │               │
             ┌─────────▼──────────────▼──┐    ┌────▼──────────────┐
             │ PostgreSQL (single DB)    │    │ Redis (Messenger  │
             │ global tables +           │    │ async transport,  │
             │ company tables with RLS   │    │ cache, locks)     │
             └───────────────────────────┘    └────┬──────────────┘
                                                      │
                                           ┌──────────▼──────────┐
                                           │  Workers            │
                                           │  AT webservices,    │──► AT (SOAP)
                                           │  PDF, seal, email   │──► Trust service provider
                                           └──────────┬──────────┘──► SMTP
                                                      │
                                           ┌──────────▼──────────┐
                                           │ Object storage (S3) │
                                           │ PDFs, SAF-T files   │
                                           └─────────────────────┘
```

### 4.1 Modules (bounded contexts)

| Module | Responsibility |
|---|---|
| **Platform** | Users, authentication, companies (tenants), memberships, roles, API tokens, company onboarding |
| **Company** | Company profile, fiscal settings, AT credentials, series management |
| **Parties** | Customers, suppliers, addresses, contacts |
| **Catalog** | Products/services, units, families, price lists |
| **Tax** | VAT rates by region and validity, exemption reasons, the single `PriceCalculator` (§7.9) |
| **Fiscal** | Drafts, issuance, signing, numbering, ATCUD, QR, documents, receipts, movement of goods, cancellation, SAF-T export — **the strict core** |
| **Accounts** | Current accounts (customers and suppliers), open items, allocations |
| **Inventory** | Warehouses, stock ledger, stock levels, valuation, counts, adjustments |
| **Purchases** | Supplier documents, goods receipts, supplier payments |
| **AtIntegration** | AT webservice clients, outbox processing, communication status |
| **Output** | PDF rendering, qualified seal, email, archive |
| **Shared** | Money, quantities, NIF value object, clock, IDs, company context |

### 4.2 Layering inside each module

```
src/Fiscal/
  Domain/          Entities, value objects, domain services, domain events, ports (interfaces)
  Application/     Commands, queries, handlers (use cases), DTOs
  Infrastructure/  Doctrine repositories, signer, AT clients, PDF adapters
  UI/Http/         Controllers, request/response DTOs, OpenAPI attributes
```

Rules, enforced in CI with **Deptrac**:
- `Domain` depends on nothing outside `Domain` and `Shared/Domain` (no Symfony, no Doctrine attributes — mapping in XML/PHP config).
- `Application` depends on `Domain`.
- `Infrastructure` and `UI` depend on `Application` and `Domain`.
- Modules communicate through application services or domain events, never by reaching into another module's repositories.

Strictness is intentional in **Fiscal**, **Tax** and **Inventory**. Simple CRUD areas (e.g. contacts, families) can be pragmatic.

---

## 5. Multi-tenancy: shared database with Row-Level Security

### 5.1 Structure

- **One PostgreSQL database** for the whole platform.
- **Global tables** (no `company_id`): users, companies, memberships, roles, API tokens, signing key versions, and reference data (document types, tax rates, exemption reasons, units, countries).
- **Company-scoped tables**: everything belonging to a company — master data, fiscal documents, stock, purchases, current accounts, audit log, AT outbox. Every one of these has a `company_id UUID NOT NULL` column.

### 5.2 Isolation: PostgreSQL Row-Level Security (RLS)

Isolation is enforced by the database, not only by application code:

```sql
ALTER TABLE documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE documents FORCE ROW LEVEL SECURITY;

CREATE POLICY company_isolation ON documents
  USING      (company_id = current_setting('app.company_id')::uuid)
  WITH CHECK (company_id = current_setting('app.company_id')::uuid);
```

- The same policy is applied to **every** company-scoped table (generated by a migration helper so none is forgotten; a CI check fails if a table with `company_id` has no policy).
- The application connects with a role that **does not own** the tables (`app_runtime`); `FORCE ROW LEVEL SECURITY` ensures policies apply even to the owner. Migrations run as a separate owner role.
- If `app.company_id` is not set, `current_setting` raises an error → queries **fail closed**, they never return all rows.
- Second layer: a Doctrine SQL filter adds `company_id = :company` to entity queries, and `company_id` is always set explicitly on insert by the repository.

### 5.3 Request flow and company resolution

- The company is explicit in the URL: `/api/v1/companies/{companyId}/...` — clear in logs, no hidden state.
- A request listener resolves `companyId` → checks membership via a Symfony Voter → sets a `CompanyContext`.
- A Doctrine/DBAL middleware runs `SET LOCAL app.company_id = '<id>'` at the start of every transaction. `SET LOCAL` is scoped to the transaction, so it is safe with connection pooling (PgBouncer in transaction mode) and cannot leak into another request.
- Every company-scoped operation runs inside an explicit transaction (Messenger's `doctrine_transaction` middleware for commands, a wrapper for queries).

### 5.4 Async work and CLI

- Every Messenger message carries a `CompanyStamp`; a middleware restores the `CompanyContext` in workers and clears it after handling.
- Console commands touching company data require `--company=<id>`.
- Platform-wide jobs (e.g. the AT outbox sweeper) first list pending work through a narrow `SECURITY DEFINER` function that returns only `(company_id, item_id)` pairs, then process each item inside that company's context. No runtime role ever has `BYPASSRLS`.

### 5.5 Company onboarding

`CreateCompany` use case: create company row → membership for the owner → default settings and warehouse → default series drafts. A single transaction; no database provisioning.

### 5.6 Indexes and constraints

- `company_id` is the **first column** of every unique constraint and most indexes: `UNIQUE (company_id, code)`, `UNIQUE (company_id, series_id, number)`, `UNIQUE (company_id, idempotency_key)`.
- Foreign keys between company tables include `company_id` where practical (composite FKs), so a row can never reference another company's row.

### 5.7 Trade-offs

| Pros | Cons |
|---|---|
| One schema, one migration run per release | Isolation depends on RLS being configured correctly → must be tested (§13) |
| Reference data stored once | A single very large tenant affects shared resources |
| Simple workers, outbox and reporting | Per-company restore is not a simple database restore |
| No provisioning, no connection switching | — |
| Platform-wide reporting (usage, billing, support) is a simple query | — |

Per-company restore is rarely meaningful for fiscal data anyway: restoring a company to an earlier point would erase documents already signed and communicated to the AT. Per-company export is covered by SAF-T plus a full JSON export (§8.3).

**Escape hatch:** because every row carries `company_id`, a very large client can later be moved to a dedicated database or cluster without redesign.

---

## 6. Data model

Conventions:
- Primary keys: **UUID v7** (time-ordered, index-friendly, safe to expose in the API).
- Money: `NUMERIC(18,2)` for document totals; `NUMERIC(19,6)` for unit prices and costs **[DECIDE]** decimal places for prices and quantities.
- Quantities: `NUMERIC(19,6)`.
- Timestamps: `TIMESTAMPTZ`, stored in UTC; fiscal dates/times are rendered in `Europe/Lisbon` (`Atlantic/Azores` where applicable **[VERIFY]** how SAF-T expects times for Azores companies).
- **Snapshots:** fiscal documents copy customer and product data at issuance. Master data can change; issued documents never do.
- Fiscal tables are **insert-only** (see §6.9).
- Every company-scoped table has `company_id` (omitted from the listings below for readability), an RLS policy, and `company_id` as the first column of its unique constraints (§5.6). Tables marked **(global)** have no `company_id`.

The listings below show the essential columns, not every column.

### 6.1 Platform tables (global)

```sql
users            (id, email UNIQUE, name, password_hash, must_change_password BOOL,
                  password_changed_at, mfa_secret NULL, status, created_at)
companies        (id, nif UNIQUE, legal_name, status   -- active|suspended|blocked
                  , plan, created_at)
memberships      (user_id, company_id, role, status, created_at)    -- PK (user_id, company_id)
roles            (code, name)                                     -- owner, admin, billing, stock, accountant, read_only
role_permissions (role_code, permission)
api_tokens       (id, company_id, name, token_hash, scopes JSONB, last_used_at, expires_at, revoked_at)
signing_keys     (version INT PK, public_key_pem, active_from, retired_at)   -- private key is NOT stored here (§8.2)
```

### 6.2 Company settings

```sql
company_profile   (nif, legal_name, commercial_name, address, postal_code, city, country,
                   share_capital, registry_office, email, phone, logo_key, fiscal_region -- PT|PT-AC|PT-MA
                  , vat_regime, cash_vat BOOL)
at_credentials    (subuser, password_encrypted, validated_at, last_error)   -- AT subuser for webservices
settings          (key, value JSONB)       -- default warehouse, payment terms, PDF template, etc.
```

### 6.3 Parties

```sql
customers  (id, code, nif, name, address, postal_code, city, country, email, phone,
            payment_terms_days, price_list_id, is_final_consumer BOOL, active, created_at, updated_at)
suppliers  (id, code, nif, name, address, postal_code, city, country, email, phone,
            payment_terms_days, active, created_at, updated_at)
addresses  (id, party_type, party_id, label, address, postal_code, city, country)   -- delivery/loading addresses
```

Notes: NIF validated with the Portuguese check-digit algorithm (value object `Nif`); foreign customers store their country VAT ID. The generic "Consumidor final" customer (NIF 999999990) is seeded **[VERIFY]** rules and limits for invoices without customer NIF.

### 6.4 Catalog

```sql
units          (code PK, name, decimals)                 -- global: UN, KG, CX, L...
product_families(id, name, parent_id)
products       (id, code, description, type       -- SAF-T ProductType: P|S|O|E|I
               , kind                               -- simple|kit (§7.10); 'assembled' reserved for later
               , unit_code, barcode, family_id, tax_rate_id, exemption_reason_code NULL,
                track_stock BOOL, last_cost, average_cost, active)
price_lists    (id, name, default_includes_vat BOOL)       -- default for new prices in this list
product_prices (product_id, price_list_id
               , amount NUMERIC(19,6)                -- the value exactly as the user entered it
               , includes_vat BOOL                   -- whether that value includes VAT (§7.9.8)
               , PRIMARY KEY (company_id, product_id, price_list_id))
product_components (kit_product_id, component_product_id, quantity NUMERIC(19,6),
                    sort_order)                      -- stock and informational use only (§7.10)
```

### 6.5 Tax (global reference data)

```sql
tax_rates         (id, region            -- PT|PT-AC|PT-MA
                  , code                 -- NOR|INT|RED|ISE|OUT (SAF-T TaxCode)
                  , percentage, valid_from, valid_to NULL, description)
exemption_reasons (code PK               -- M01..M99 [VERIFY] current official list
                  , description, legal_reference, valid_from, valid_to NULL)
```

Tax rates and exemption reasons are global and versioned by validity dates; changes (e.g. a new State Budget) ship as data migrations. All calculations go through the single `PriceCalculator` described in §7.9.

### 6.6 Fiscal core

```sql
document_types (code PK                  -- global
                                -- FT, FS, FR, NC, ND, RG, GT, GR, GD, OR, PF, NE
               , saft_section            -- SalesInvoices | MovementOfGoods | WorkingDocuments | Payments
               , signed BOOL             -- false for RG (receipts are not signed)
               , stock_effect            -- out|in|none
               , account_effect          -- debit|credit|none
               , requires_at_prior_communication BOOL)   -- true for GT/GR/GD

series (
  id, document_type, code                -- e.g. '2026A'
, is_training BOOL
, validation_code NULL                   -- returned by AT when the series is communicated
, status                                 -- draft|active|finished|cancelled
, first_number, last_number, last_hash NULL
, last_issue_date NULL, last_system_entry_at NULL
, at_communicated_at NULL, at_finished_at NULL
, UNIQUE (company_id, document_type, code)
)
```

**Drafts** (mutable, not fiscal):

```sql
document_drafts (id, document_type, series_id NULL, customer_id NULL, payload JSONB,
                 calculated JSONB, created_by, updated_at)
```

Drafts are never printable or sendable as documents: once something is presented to the customer, it is fiscally relevant. The UI shows an on-screen preview only.

**Issued documents** (insert-only):

```sql
documents (
  id, document_type, series_id, number INT
, document_no                            -- 'FT 2026A/15'
, atcud                                  -- '<validation_code>-15'
, issue_date DATE, system_entry_at TIMESTAMPTZ
, customer_id NULL
, customer_snapshot JSONB                -- nif, name, address, country at issuance
, issuer_snapshot JSONB                  -- company name, NIF, address, capital, registry at issuance
, template_version                       -- PDF layout version used for this document (§7.8)
, pricing_mode                           -- net|gross (§7.9)
, rounding_method                        -- per_line|per_group (§7.9.5)
, currency CHAR(3), exchange_rate NULL
, global_discount_percent NULL, settlement_total NUMERIC(18,2)
, net_total, tax_total, gross_total      -- NUMERIC(18,2)
, withholding_total NULL
, payment_terms JSONB NULL, due_date NULL
, hash, hash_control                     -- signature + key version
, qr_payload TEXT
, is_training BOOL
, status CHAR(1)                         -- N, A, F... (current status, see status events)
, status_at, status_reason NULL
, source_id                              -- user who issued (SAF-T SourceID)
, issued_via                             -- web|api
, idempotency_key NULL
, UNIQUE (company_id, series_id, number)
, UNIQUE (company_id, idempotency_key)
)

document_lines (
  id, document_id, line_number
, product_id NULL, product_code, product_description, product_type, unit_code
, quantity, unit_price                   -- NUMERIC(19,6), in the document's pricing mode
, discount_percent, discount_amount
, settlement_amount                      -- share of the global discount (§7.9.4)
, net_amount, gross_amount               -- NUMERIC(19,6), line level for SAF-T
, tax_region, tax_code, tax_percentage, tax_amount
, exemption_reason_code NULL, exemption_reason_text NULL
, tax_point_date NULL
, origin_references JSONB NULL           -- OrderReferences (e.g. GR that originated this line)
)

document_tax_summary (document_id, tax_region, tax_code, tax_percentage, taxable_base, tax_amount)

document_references (document_id, referenced_document_no, reason)   -- NC/ND → original invoices

document_status_events (id, document_id, status, reason, user_id, occurred_at)  -- history, insert-only
```

**Movement of goods** (GT/GR/GD) — extra data, also insert-only:

```sql
movement_details (document_id PK, loading_address JSONB, loading_at TIMESTAMPTZ,
                  unloading_address JSONB, vehicle_plate NULL,
                  at_doc_code NULL, at_communicated_at NULL)   -- AT code printed on the document
```

**Receipts** (RG, SAF-T Payments — not signed):

```sql
receipts            (id, series_id, number, document_no, atcud, issue_date, system_entry_at,
                     customer_id, customer_snapshot JSONB, total, payment_method, status, source_id,
                     UNIQUE (company_id, series_id, number))
receipt_allocations (receipt_id, document_id, amount, settlement_amount)
```

### 6.7 Current accounts

```sql
account_entries (id, party_type, party_id, source_type, source_id, entry_date, due_date,
                 debit, credit, open_amount, status)         -- open|settled
account_allocations (id, debit_entry_id, credit_entry_id, amount, allocated_at)
```

Entries are created by issuance (invoices, credit notes), receipts, supplier documents and supplier payments. Balances are derived; `open_amount` is maintained by allocation.

### 6.8 Working documents (v1)

Quotes (OR), proformas (PF) and customer orders (NE) are included in v1: they are fiscally relevant when presented to the customer, belong to the SAF-T `WorkingDocuments` section, and being part of the program from the start means they are covered by certification rather than added to a certified product later. **[VERIFY]** the current list of `WorkType` codes and whether any other working document types are worth supporting (e.g. FO – folha de obra, OU – outros).

Rules:
- Same issuance pipeline as §7.1: own series, sequential numbering, ATCUD, signature chain, QR code where required **[VERIFY]**, immutable once issued.
- Printed with the mention **"Este documento não serve de fatura"**.
- No stock movements and no current-account entries.
- Status: issued documents can be cancelled (status `A`) or marked as converted/invoiced (status `F`), never edited. A changed quote is a new quote.
- **Conversion flows** create a new draft prefilled from the source document, with `origin_references` (SAF-T `OrderReferences`) pointing back:
  `OR → NE → GR/GT → FT`, `OR → FT`, `PF → FT/FR`, `NE → FT`, including partial conversion (part of the lines/quantities).
- The system tracks converted quantities per source line, so a document can show what is still pending (e.g. order partially delivered).
- Customer orders and stock: **[DECIDE]** whether an NE reserves stock (recommendation: show "committed" quantities for information, no hard reservation in v1).
- Communication to the AT: **[VERIFY]** whether working documents must be communicated in the monthly SAF-T/webservice, and include them in the SAF-T export in any case.

### 6.9 Enforcing immutability in PostgreSQL

- The application DB role has `INSERT, SELECT` on `documents`, `document_lines`, `document_tax_summary`, `document_references`, `document_status_events`, `movement_details`, `receipts`, `receipt_allocations`, `stock_movements`, `audit_log` — **no `DELETE`**.
- `UPDATE` on `documents`/`receipts` is allowed only through a trigger that rejects any change except to `status`, `status_at`, `status_reason` (cancellation) — and only if a matching row exists in `document_status_events`.
- `UPDATE` on `movement_details` allowed only for `at_doc_code` and `at_communicated_at`, once.
- `series.last_number` can only increase by exactly one per issuance, and `last_issue_date` / `last_system_entry_at` can never move backwards (trigger check).
- Migrations run with a separate owner role; the app connects as `app_runtime`, which is subject to RLS and immutability rules (§5.2).

### 6.10 Inventory

```sql
warehouses       (id, code, name, address, is_default BOOL, active)

stock_movements  (                         -- append-only ledger, the source of truth
  id, product_id, warehouse_id, occurred_at
, quantity                                 -- positive in, negative out
, unit_cost                                -- cost at the time of movement
, source_type, source_id                   -- document, supplier_document, adjustment, count, transfer
, source_line_id NULL                      -- document line that caused it (e.g. the kit line, §7.10)
, lot_id NULL                              -- reserved for future lot traceability
)

stock_levels     (product_id, warehouse_id, quantity, average_cost, updated_at)  -- projection

stock_adjustments(id, warehouse_id, reason, created_by, created_at)
stock_counts     (id, warehouse_id, status, started_at, closed_at)
stock_count_lines(count_id, product_id, counted_quantity, system_quantity)
stock_transfers  (id, from_warehouse_id, to_warehouse_id, document_id NULL, created_at)
```

Rules:
- Kits (§7.10) have no stock of their own: selling a kit creates one outbound movement per component, linked to the kit's document line.
- Stock moves **once** per physical movement: if an invoice references a GR that already moved stock, the invoice lines do not move stock again (`origin_references`).
- Valuation: **weighted average cost** (custo médio ponderado), recalculated on every inbound movement **[DECIDE]** confirm method; FIFO only if a vertical client needs it.
- Negative stock: **[DECIDE]** block, warn, or allow per company setting.
- `stock_levels` is updated in the same transaction as the movement (row lock per product/warehouse).
- Annual inventory communication to the AT **[VERIFY]** current thresholds, deadline and file format — build the export in v1 if target clients are obliged.

### 6.11 Purchases

```sql
supplier_documents (id, supplier_id, type                   -- invoice|credit_note|goods_receipt|debit_note
                   , supplier_document_no, issue_date, due_date, warehouse_id,
                    net_total, tax_total, gross_total, status, attachment_key NULL,
                    created_by, created_at,
                    UNIQUE (company_id, supplier_id, type, supplier_document_no))
supplier_document_lines (id, supplier_document_id, product_id NULL, description,
                         quantity, unit_cost, discount_percent, tax_code, tax_percentage,
                         net_amount, tax_amount)
supplier_payments  (id, supplier_id, payment_date, amount, method, reference)
supplier_payment_allocations (payment_id, supplier_document_id, amount)
```

Supplier documents are **recorded, not issued**: they are not signed and do not enter the sales SAF-T. They can be corrected, but every change is written to the audit log. Posting a goods receipt or invoice creates inbound stock movements and a supplier account entry.

### 6.12 AT outbox, audit and files

```sql
at_communications (
  id, kind                                 -- series_register|series_finish|invoice|transport
, subject_type, subject_id
, status                                   -- pending|sending|accepted|rejected|failed
, attempts, next_attempt_at
, request_digest, response_code, response_message, at_reference NULL
, created_at, updated_at
)

audit_log (id, occurred_at, user_id NULL, api_token_id NULL, action, subject_type, subject_id,
           data JSONB, ip, user_agent)       -- insert-only

document_prints (id, document_id, kind   -- print|download|email
                , copy_label, user_id, occurred_at)    -- insert-only; drives original/copy mentions (§7.8)

stored_files (id, kind                     -- sealed_pdf|saft|attachment
             , subject_type, subject_id, storage_key, sha256, size, created_at)
```

---

## 7. Core flows

### 7.1 Document issuance

`POST /companies/{c}/documents/drafts/{id}/issue` with header `Idempotency-Key`.

```
 1. Idempotency check      → key already used? return the original result (same document)
 2. Load + validate draft  → customer, NIF, lines, exemption reason on 0% lines,
                              references for NC/ND, series matches document type
 3. BEGIN; SET LOCAL app.company_id = …
 4. SELECT … FROM series WHERE id = ? FOR UPDATE
      → series active, has AT validation code, not training/real mismatch
 5. Chronology checks      → issue_date >= series.last_issue_date
                              system_entry_at >= series.last_system_entry_at
 6. number = last_number + 1
 7. PriceCalculator        → canonical lines, tax summary, totals (never trust client totals, §7.9)
 8. Sign                   → build signing string with previous hash, sign with active key version
 9. ATCUD + QR payload
10. INSERT document, lines, tax summary, references, status event
11. UPDATE series          → last_number, last_hash, last_issue_date, last_system_entry_at
12. Side effects (same transaction):
      stock movements · account entry · audit log · at_communications row (pending)
13. Store idempotency result, delete draft
14. COMMIT
15. After commit (async):  AT communication · PDF render · qualified seal · email (if requested)
```

Anything in steps 3–14 failing rolls everything back: no number is consumed, no gap is created.

### 7.2 Signing (Despacho 8632/2014)

- `DocumentSigner` port; infrastructure adapter using OpenSSL.
- Signing string **[VERIFY]** exact format and field formatting in the Despacho:
  `InvoiceDate;SystemEntryDate;InvoiceNo;GrossTotal;PreviousHash`
  (`YYYY-MM-DD;YYYY-MM-DDThh:mm:ss;FT 2026A/15;123.45;<previous hash or empty for the first document>`).
- Result stored as base64 (`hash`) plus key version (`hash_control`).
- Printed: characters 1, 11, 21 and 31 of the hash + `-Processado por programa certificado n.º XXXX/AT`.
- Receipts (not signed) print `Emitido por programa certificado n.º XXXX/AT`.
- Training documents print the training mention **[VERIFY]** exact text.
- Algorithm and key size exactly as the Despacho specifies **[VERIFY]**; the private key never leaves the signing service (§8.2).

### 7.3 Movement of goods

- GT/GR/GD must be communicated to the AT **before goods start moving**. After commit, the API calls the AT transport webservice synchronously (short timeout) and returns the document with its `at_doc_code`.
- If the AT is unavailable: the document stays issued with `at_communications.status = failed`, the UI shows a blocking warning and retry action, and the fallback procedures allowed by law are documented for the user **[VERIFY]** current fallback rules.
- Converting a GR into an invoice creates the invoice with `origin_references` → no second stock movement.

### 7.4 Cancellation and correction

- **Cancel** (status `A`): allowed only under the legal conditions **[VERIFY]** (e.g. not yet communicated / not accepted by customer); writes a status event, reverses stock and account effects with new compensating entries (never deleting), and communicates to the AT where applicable.
- **Correct:** credit note (NC) or debit note (ND) referencing the original document(s). The API offers `POST /documents/{id}/credit-note` to prefill a draft.

### 7.5 AT communication (outbox pattern)

- The `at_communications` row is written in the same transaction as the document → nothing is ever lost.
- After commit, a Messenger message `CommunicateToAt(companyId, communicationId)` is dispatched to Redis.
- A scheduled sweeper (every few minutes) finds `pending`/`failed` rows past `next_attempt_at` as a safety net (§5.4).
- Retries with exponential backoff; permanent AT rejections (validation errors) go to `rejected` and surface in the UI.
- Webservices: series (register/finish/consult), invoice data communication, transport documents. SOAP with the AT's WS-Security requirements (credentials encrypted with the AT's public key, client certificate) **[VERIFY]** current specs and test environment.
- Companies can alternatively rely on the monthly SAF-T submission (deadline: day 5 of the following month, adjusted by the official calendar); the dashboard shows communication status per month either way.

### 7.6 Series lifecycle

`draft` → communicate to AT (`series_register`) → receive `validation_code` → `active` → issue documents → `finished` (communicated to AT at year end or when replaced). A series cannot issue documents without a validation code.

### 7.7 SAF-T (PT) export

- Streaming generation with `XMLWriter` (no full document in memory); files stored in object storage.
- Types: full billing export for a period, and the monthly communication export **[VERIFY]** differences required for each.
- Validated against the official XSD in the application (before download) and in CI with fixture data.
- Master data sections include only customers/products referenced in the period **[VERIFY]**.

### 7.8 PDF, qualified seal and email

**PDFs are generated on demand**, not stored. Issued documents are immutable and contain every value the PDF needs, so a PDF is a deterministic rendering of stored data, produced when someone downloads, prints or emails it.

- `DocumentPdfRenderer` port (module *Output*) with one implementation behind it; engine **[DECIDE]**: a PHP library (e.g. mPDF: no extra service, simpler operations) or Twig → Gotenberg/Chromium (best layout fidelity, one more container). The port makes the engine replaceable.
- Rendering uses **only stored document data**: lines, totals, `customer_snapshot` and `issuer_snapshot` (so a later change of company address, logo or customer data never alters old documents).
- **Template versioning:** each document records `template_version`; old template versions stay in the codebase so a document re-rendered years later looks as it did when issued. New layouts create a new version.
- Contents: all legal mentions, ATCUD, QR code, 4 hash characters + certification mention, AT code for transport docs, "Este documento não serve de fatura" for working documents.
- **Original / copies:** every print, download and email is logged in `document_prints`, so the renderer can apply the correct mentions (e.g. original, duplicate, reprint/2.ª via) **[VERIFY]** exact rules for copy and reprint mentions in the Despacho.
- Optional short-lived cache (e.g. a few minutes) for repeated downloads of the same unsealed PDF; the cache is never a source of truth.

**Sealed PDFs are the exception and are stored.** From 1 January 2027, PDFs sent electronically receive a qualified electronic seal via an `ElectronicSealer` port and a qualified trust service provider **[DECIDE]** provider. The sealed file is a signed artifact: each seal has a cost and a timestamp, and re-sealing on every download would produce a different file each time. The exact file sent to the customer is therefore stored once (object storage, SHA-256, object lock / WORM for the 10-year retention) and re-downloaded as is **[VERIFY]** archiving obligations for electronic invoices in DL 28/2019.

**Seal model:**
- Each client company needs **its own** qualified electronic seal certificate (the seal identifies the issuing company); a TCWeb certificate cannot seal other companies' invoices **[VERIFY]**.
- Qualified keys live in a qualified signature creation device (the provider's HSM, or a chip) and cannot be exported to the server as a file. The platform therefore uses **remote sealing**: PHP renders the PDF, computes its hash, the provider signs the hash, and PHP embeds the signature into the PDF (PAdES). The PDF itself never leaves the platform.
- Onboarding: the company obtains the seal certificate from the provider (identity checks are the provider's), then links it in company settings.
- Personal qualified signatures (Cartão de Cidadão with professional attributes, Chave Móvel Digital) require the representative's interaction per signature, so they do not fit automatic sealing; a manual "sign with CC/CMD" flow may be considered after v1.
- Only documents **sent electronically** are sealed and stored; documents only printed on paper are not.

**Storage sizing:** growth is linear with the number of electronically sent documents, not exponential. Example: 300 companies × 500 sent documents/month × ~100 KB ≈ 15 GB/month (~180 GB/year). Mitigations: optimised PDFs (font subsetting, compressed logos), lifecycle rules moving files older than N months to a cheaper storage class, storage limits per plan.

Object storage therefore holds only: sealed PDFs, SAF-T export files, and attachments (e.g. scanned supplier documents).

### 7.9 Pricing, VAT and rounding

This section exists because of a recurring real-world problem: **cent differences** between what a web shop (or the UI) shows and what the invoice says, and confusion between the extra decimal places used in SAF-T and the 2 decimals of real money.

#### 7.9.1 Root causes of cent differences

1. **Two implementations** of the same maths (JavaScript in the browser or shop, PHP in the backend), usually with **floats**.
2. **Gross vs net anchoring:** shops show VAT-inclusive prices (9,99 €), invoices are often computed from net prices. Deriving the net price, rounding it to 2 decimals and adding VAT back does not always return the original gross price.
3. **Rounding order:** per line vs per document, and the order in which line discounts, global discounts and VAT are applied.

**Example — the classic lost cent.** Product shown at 9,99 € (VAT 23 %), quantity 3.

| Approach | Net | VAT | Total |
|---|---|---|---|
| Shop (gross) | — | — | **29,97 €** |
| Naive invoice: net unit 9,99 / 1,23 = 8,1219… → 8,12 × 3 = 24,36; VAT 24,36 × 23 % = 5,6028 → 5,60 | 24,36 | 5,60 | **29,96 €** ✗ |
| Gross-anchored (this system): gross 29,97 fixed; net = 29,97 / 1,23 = 24,3658… → 24,37; VAT = 29,97 − 24,37 | 24,37 | 5,60 | **29,97 €** ✓ |

#### 7.9.2 Principles

1. **One calculator.** `PriceCalculator` (domain service, module *Tax*) is the only code that computes lines, discounts, VAT and totals. The UI, the API, e-commerce integrations and issuance all use it (via `/calculate` or at issuance). No other implementation exists.
2. **No floats anywhere.** `brick/math` (`BigDecimal`) and `brick/money` in PHP; `NUMERIC` in PostgreSQL; decimals as strings in the API and the frontend. A PHPStan rule forbids `float` in `Domain` namespaces of *Tax*, *Fiscal*, *Inventory*, *Accounts*.
3. **Explicit pricing mode per document:**
   - `net` — prices exclude VAT (typical B2B). Net amounts are the anchor; VAT is derived.
   - `gross` — prices include VAT (typical B2C, web shops). **Gross amounts are the anchor**; net and VAT are derived, so the total always equals what the customer saw.
4. **The price the customer saw is the price on the invoice.** Integrations send the prices they displayed and the mode; the system reproduces the same total to the cent.
5. **Rounding happens in exactly defined places**, with one rounding mode: `HALF_UP` **[VERIFY]** against AT guidance.

#### 7.9.3 Precision tiers

| Tier | Precision | Used for |
|---|---|---|
| Calculation | 10 decimals (`BigDecimal` scale), never rounded mid-calculation | Intermediate values |
| Unit price | stored with up to 6 decimals **[DECIDE]** | Net unit prices derived from gross, cost prices |
| Line amounts | stored with 6 decimals, exported to SAF-T with the precision the XSD allows **[VERIFY]** | SAF-T `UnitPrice`, `CreditAmount`/`DebitAmount`, `SettlementAmount` |
| Document totals and VAT per rate | **2 decimals** (cents) | What is printed, signed (`GrossTotal`), paid, and posted to current accounts |

So the "4 decimals vs 2 decimals" conflict is resolved by design: **higher precision lives at line level for SAF-T; money that people see, pay or sign is always 2 decimals**, and the totals are derived from line values by a single, deterministic rule.

#### 7.9.4 Calculation algorithm (v1)

**Order of operations (fixed):** price × quantity → line discount → global discount → **VAT last**. Discounts always reduce the taxable base; VAT is never calculated before discounts and discounts are never applied to VAT.

For each line:
1. `line_amount = quantity × unit_price` (full precision).
2. Apply the line discount(s): percentage, cascading percentages (e.g. 10 % + 5 %) or a fixed amount **[DECIDE]** which forms to support in v1.

Document level:
3. Apply the global discount (if any) and **allocate it to lines proportionally** (`SettlementAmount`), using the largest-remainder method so allocations sum exactly to the discount.
   The discounted line amount is the **taxable base** of the line (SAF-T: unit price net of line and header discounts, discount amounts in `SettlementAmount` **[VERIFY]** exact field semantics in the technical notes).

In **gross mode** the same order applies. Percentage discounts give the same result whether applied to the gross or the net value (they are proportional), so the calculator applies them to the gross amount and then splits the discounted gross into base + VAT: VAT is still calculated on the discounted base, as the law requires. **Fixed-amount discounts** must state whether they are net or gross; by default they follow the document's pricing mode.
4. Every line keeps its own tax key `(region, tax_code, percentage)` — documents freely mix rates (23 %, 13 %, 6 %, exempt).
5. Compute VAT using the document's **rounding method** (§7.9.5).
6. Document totals: `net_total = Σ base`, `tax_total = Σ vat`, `gross_total = net_total + tax_total` (all exact 2-decimal sums), plus the VAT summary per tax key required on the invoice.
7. Validation invariants checked at issuance: totals equal the sum of the VAT summary; SAF-T line values reproduce the summary under the chosen method.

#### 7.9.5 Rounding method: per line or per rate group

Both methods support mixed rates; they differ in **where the VAT amount is rounded**:

| Method | Net mode | Gross mode |
|---|---|---|
| `per_line` | per line: `net = round2(line net)`, `vat = round2(net × rate)`; group = Σ lines | per line: `gross = round2(line gross)`, `net = round2(gross / (1 + rate))`, `vat = gross − net`; group = Σ lines |
| `per_group` | per tax key: `base = round2(Σ line net)`, `vat = round2(base × rate)` | per tax key: `gross = round2(Σ line gross)`, `base = round2(gross / (1 + rate))`, `vat = gross − base` |

Example — three lines of 0,10 € net at 23 %: `per_line` → 3 × 0,02 = **0,06 €** VAT; `per_group` → 0,30 × 23 % = 0,069 → **0,07 €** VAT.

- Default method is a **company setting**; integrations can **override it per document** via the API so the invoice matches the rounding method of the shop it is connected to (e.g. WooCommerce's "round tax at subtotal level" option).
- The method is stored on the document (`rounding_method`) so it can always be recalculated and explained.
- SAF-T line values are exported with the extra precision needed for the AT to reproduce the totals under either method **[VERIFY]** against the SAF-T technical notes and the XSD precision limits.

Withholding tax (retenção na fonte), where applicable, is computed on the 2-decimal base and shown separately.

#### 7.9.6 Shared test vectors

A versioned `pricing-test-vectors.json` in the repository defines inputs (pricing mode, rounding method, lines, quantities, prices, discounts, rates) and exact expected outputs (line amounts, groups, totals). It is used by:
- the PHP `PriceCalculator` test suite,
- the web app (to verify display formatting and that it only shows server values),
- **e-commerce stores built by TCWeb** (WooCommerce, custom shops): their cart logic must pass the same vectors, or call the `/calculate` endpoint at checkout.

Any change to the calculation rules is a new vector version, reviewed like a fiscal change.

#### 7.9.7 Display rules

- Totals, VAT and line totals: always 2 decimals.
- Unit prices: 2 decimals by default; show up to the stored precision only when needed (e.g. 0,0450 €/un) **[DECIDE]**.
- PDFs and UI show exactly the stored values — nothing is recalculated for display.

#### 7.9.8 Product prices with or without VAT

Some users think in shelf prices (VAT included), others in net prices. Both are first-class:

- Each price is stored **exactly as entered**, with a flag: `amount` + `includes_vat` (§6.4). The system never overwrites the entered value with a converted one.
- The product form has a "Preço com IVA / sem IVA" switch and shows the other value live (calculated by the server, display only).
- **When a VAT rate changes** (e.g. State Budget), the entered value is kept: a VAT-inclusive price stays at 9,99 € and its net value changes; a net price stays and its gross value changes. This matches what each type of user expects.
- **Document pricing mode default:** taken from the price list/customer (B2C → gross, B2B → net), overridable per document.
- **Conversion into a document of the other mode** (e.g. a gross price used in a net-mode document): the unit price is converted at 6 decimals (`9,99 / 1,23 = 8,121951`). Because converting can shift totals by a cent, the editor warns when a document mixes lines whose prices were entered in the other mode, and the recommended practice is to keep B2C documents in gross mode.
- The VAT rate used for conversion is the rate applicable to the line (product rate, company fiscal region, exemptions), resolved by the `PriceCalculator`.

### 7.10 Composite products (kits)

A kit is a product composed of a specific set of existing products (e.g. a "Kit Misto" of several smoked products).

#### 7.10.1 v1 scope: sales kits (exploded at sale)

- `products.kind = kit`, components in `product_components` (component, quantity). Kits have **no stock of their own**: when sold, stock is moved for each component.
- Availability of a kit = the minimum, across components, of `component stock / component quantity`.
- Kit cost = Σ component average cost × quantity (for margins and valuation).
- Nested kits: not in v1 **[DECIDE]**; if allowed later, with cycle detection.
- The composition used is recorded through the stock movements of each sale: changing a kit's composition never changes past documents or movements.

#### 7.10.2 Pricing and VAT: the kit is a single item

- For pricing, VAT and documents, a kit behaves **exactly like any other product**: the user defines its price (with or without VAT, §7.9.8), its VAT rate and exemption reason.
- On documents it is **one line** with the kit's price and VAT rate. Components never appear as document lines, never enter the VAT summary and never appear in SAF-T; discounts apply to the kit line like any other line.
- The composition is used only for:
  - **stock:** selling the kit creates one outbound stock movement per component, linked to the kit's document line (`source_line_id`);
  - **cost and margin:** kit cost = Σ component average cost × quantity;
  - **information:** exposed through the API (e.g. `GET /products/{id}` includes components) for custom websites or catalogues built for the client.
- When the kit's VAT rate differs from the rates of its components, the product form shows an informational warning, since mixed-rate bundles may have specific VAT rules **[VERIFY]**; the decision stays with the user.

#### 7.10.3 Later: assembled products (bill of materials)

Products assembled in advance and stocked as themselves (assembly order consumes components and produces kit stock, with cost roll-up). This is the entry point to production features for vertical clients (e.g. food processing) and is planned after v1; `kind = 'assembled'` is reserved in the model.

---

## 8. Security and certification requirements

### 8.1 Access control (Despacho requirements and good practice)

- Individual authenticated users; no shared accounts.
- Password must be changed at first login; cannot be empty; administrators can never see or set a known password (invite links / reset flows only). Full list per `docs/legal/despacho-8632-2014.pdf` §3.1.1: force a password change on first access and whenever otherwise necessary, the new password cannot be empty, the administrator can never know or view it, and an administrator-triggered reset must itself be changed as soon as the user accesses it. The Despacho sets no minimum length or complexity — that's this product's own policy, decided by the owner: minimum 6 characters, at least one uppercase letter, one lowercase letter, one digit and one special character.
- Permission-based roles per company (issue documents, cancel, manage series, stock, purchases, settings, read-only, accountant).
- MFA (TOTP) available, recommended for owners/admins.
- Every fiscal action and every sensitive setting change goes to `audit_log`.

### 8.2 Secrets

- **Signing private key:** stored encrypted in a secrets manager, loaded only by the signing service, versioned (`hash_control`). Never in the repository, database or logs. Key rotation procedure documented.
- **AT credentials** per company: encrypted at rest (libsodium, key from secrets manager).
- **Qualified seal certificate:** held by the trust service provider (remote signing) where possible.

### 8.3 Data protection

- Company isolation by PostgreSQL RLS + Doctrine filter + membership voter on every request, covered by automated isolation tests (§13).
- Company export: SAF-T plus a full JSON/CSV export of all company data (also used when a client leaves).
- GDPR: deletion of personal data after the legal retention period for fiscal documents.
- Backups: continuous (PITR) plus daily logical dumps; tested restore procedure, including restoring a copy to extract one company's data if ever needed.

### 8.4 Certification process

1. Build to spec with full test coverage of fiscal rules (§13).
2. Prepare the certification dossier and Modelo 24 declaration with the public key.
3. Submit via Portal das Finanças; respond to conformity tests.
4. Version control of certified releases; changes affecting fiscal behaviour reviewed against the Despacho before release.

---

## 9. API design

### 9.1 Conventions

- Base path: `/api/v1`, company-scoped routes under `/api/v1/companies/{companyId}`.
- JSON, `snake_case` fields, ISO 8601 dates, decimals as **strings** (`"123.45"`) to avoid float issues in clients.
- OpenAPI 3.1 generated from code (NelmioApiDocBundle + request/response DTOs with `#[MapRequestPayload]` and Symfony Validator) (decided: Nelmio, because most fiscal endpoints are commands, not CRUD resources).
- Errors: RFC 9457 `application/problem+json` with stable machine-readable `type` codes (e.g. `series-not-active`, `nif-invalid`, `chronology-violation`).
- Pagination: cursor-based (`?cursor=…&limit=50`); filtering and sorting via query params.
- `Idempotency-Key` required on all issuing/communicating endpoints.
- `/calculate` is available to integrations (e.g. shop checkouts) so they can obtain exact totals before creating documents (§7.9).
- Versioning: additive changes only within `v1`; OpenAPI diff checked in CI to catch breaking changes.

### 9.2 Authentication

- **Web app:** session cookie (httpOnly, Secure, SameSite=Lax) + CSRF protection; login, logout, password reset, MFA.
- **Integrations:** company-scoped API tokens with scopes (`documents:read`, `documents:draft`, `documents:issue`, `stock:read`…). OAuth2 later.

### 9.3 Main endpoints (v1)

```
Auth & platform
  POST   /auth/login · /auth/logout · /auth/password/reset · /auth/mfa
  GET    /me                              user + companies + permissions
  POST   /companies                       create company (provisioning)

Company
  GET/PUT /companies/{c}/profile
  PUT     /companies/{c}/at-credentials   + POST …/at-credentials/test
  GET/POST /companies/{c}/series
  POST    /companies/{c}/series/{id}/communicate · …/finish
  GET/POST/PUT /companies/{c}/users       memberships and roles

Master data (CRUD + search)
  /companies/{c}/customers · /suppliers · /products · /product-families
  /companies/{c}/price-lists · /warehouses · /units
  GET /companies/{c}/tax-rates · /exemption-reasons

Fiscal documents
  POST   /companies/{c}/documents/drafts
  PUT    /companies/{c}/documents/drafts/{id}
  POST   /companies/{c}/documents/drafts/{id}/calculate     canonical totals for the UI
  POST   /companies/{c}/documents/drafts/{id}/issue         Idempotency-Key
  GET    /companies/{c}/documents?type=&status=&customer=&from=&to=
  GET    /companies/{c}/documents/{id}
  GET    /companies/{c}/documents/{id}/pdf
  POST   /companies/{c}/documents/{id}/send                 email
  POST   /companies/{c}/documents/{id}/cancel
  POST   /companies/{c}/documents/{id}/credit-note          prefilled draft
  POST   /companies/{c}/documents/{id}/convert              draft of target type (OR→NE/FT, NE→GR/FT, PF→FT…), full or partial
  POST   /companies/{c}/documents/{id}/at/retry

Receipts
  POST   /companies/{c}/receipts                            Idempotency-Key
  GET    /companies/{c}/receipts · /receipts/{id} · /receipts/{id}/pdf

Current accounts
  GET    /companies/{c}/customers/{id}/account              entries, balance, open items
  GET    /companies/{c}/suppliers/{id}/account

Inventory
  GET    /companies/{c}/stock?warehouse=&product=
  GET    /companies/{c}/stock/movements
  POST   /companies/{c}/stock/adjustments · /stock/transfers
  POST   /companies/{c}/stock/counts · PUT …/{id}/lines · POST …/{id}/close

Purchases
  /companies/{c}/supplier-documents (CRUD + post)
  /companies/{c}/supplier-payments

Compliance & reporting
  POST   /companies/{c}/saft-exports       async → GET …/{id} (status + download)
  GET    /companies/{c}/at/communications  status overview
  GET    /companies/{c}/reports/sales · /reports/vat · /reports/stock-valuation
  GET    /companies/{c}/audit-log
```

---

## 10. Infrastructure

### 10.1 Local development (Docker Compose)

FrankenPHP (or PHP-FPM + Caddy) · PostgreSQL 16 · Redis · Mailpit · MinIO (S3, for sealed PDFs, SAF-T files and attachments) · Vite dev server (+ Gotenberg only if chosen as PDF engine). One `make up` to start everything; seed script creating a demo company with sample data.

### 10.2 Production (initial)

- App servers: containerised Symfony API + Messenger workers (separate processes for AT, PDF/seal, email queues).
- Managed PostgreSQL with PITR (or self-managed with WAL archiving); PgBouncer in transaction mode when connection counts grow (compatible with `SET LOCAL`, §5.3).
- Redis (Messenger transport, cache, rate limiting).
- S3-compatible object storage in the EU with object lock for fiscal files.
- Hosting in the EU **[DECIDE]** provider.
- Monitoring: Sentry (errors), structured logs, uptime checks, alerts on AT communication failures and failed migrations.

### 10.3 CI/CD

- Backend: PHP-CS-Fixer, PHPStan (max level), Deptrac, PHPUnit (unit + integration against real PostgreSQL), SAF-T XSD validation, OpenAPI diff.
- Frontend: ESLint, TypeScript strict, Vitest, Playwright e2e for critical flows.
- Deploy: build images → run migrations (backward compatible: expand → deploy → contract) → deploy → smoke tests.

### 10.4 What to revisit as it grows

- Very large companies: move them to a dedicated database/cluster (possible because every row carries `company_id`).
- Table growth: partition the largest tables (`documents`, `document_lines`, `stock_movements`, `audit_log`) by `company_id` hash or by date.
- Heavy reporting: read replicas or a reporting store.

---

## 11. Frontend (web)

### 11.1 Stack

Vite · React · TypeScript (strict) · TanStack Router · TanStack Query · TanStack Table · React Hook Form + Zod · shadcn/ui + Tailwind · ECharts · API client generated from OpenAPI (orval).

### 11.2 Structure

```
web/src/
  app/            router, providers, layout, auth guard, company context
  api/            generated client + query hooks (never hand-written fetch calls)
  components/     shared UI (tables, money/quantity inputs, NIF input, status badges)
  features/
    auth/  dashboard/  customers/  suppliers/  products/
    documents/     list, draft editor, detail, PDF preview, cancel, credit note
    receipts/  transport/  accounts/
    purchases/  stock/  settings/  saft/
  lib/            formatting (pt-PT numbers, dates), permissions helpers
```

### 11.3 Principles

- **No fiscal logic in the browser.** The draft editor calls `/calculate` (debounced) and displays the server's canonical totals; the editor has a net/gross pricing mode switch (§7.9).
- Money and quantities handled as strings/decimals, formatted with `Intl.NumberFormat('pt-PT')`.
- Company switcher in the header; the active company is part of every route (`/c/{companyId}/…`).
- Permissions from `/me` drive what the UI shows; the API enforces them regardless.
- Keyboard-friendly document editor (fast line entry is a key selling point for daily users).
- Route-level code splitting; lists with server-side pagination and filters.
- i18n-ready, pt-PT only in v1.
- Visual identity: to be designed (separate product brand or TCWeb sub-brand **[DECIDE]**).

---

## 12. Roadmap

Phases are sequential in focus but overlap in practice. Each ends with a demo on real-looking data.

| Phase | Scope | Exit criteria |
|---|---|---|
| **0. Foundations** | Monorepo, Docker env, CI, Deptrac rules, auth, companies and memberships, RLS setup + `SET LOCAL` middleware + isolation tests, React shell with login and company switcher | Create a company, log in, switch companies; isolation tests and CI green |
| **1. Master data** | Company profile, customers, suppliers, products, families, units, price lists, tax rates, exemption reasons, warehouses | Full CRUD in API and web |
| **2. Fiscal core** | Series, drafts, PriceCalculator + test vectors, issuance pipeline, signing, ATCUD, QR, FT/FS/FR/NC/ND, OR/PF/NE and conversions, receipts, cancellation, immutability triggers, audit log, training mode | Concurrency and golden-file tests pass; documents immutable at DB level |
| **3. Output & compliance** | PDF templates, qualified seal integration, email, SAF-T export + XSD validation, AT webservices (series, invoices), outbox and sweeper, AT status UI | Documents communicated in the AT test environment; valid SAF-T |
| **4. Movement of goods** | GT/GR/GD, AT transport webservice, GR → invoice conversion | Transport documents obtain AT codes in test environment |
| **5. Stock & purchases** | Stock ledger, levels, average cost, sales kits (§7.10), adjustments, transfers, counts, supplier documents, supplier payments, current accounts | Stock and accounts reconcile with documents |
| **6. Hardening & certification** | Security review, performance tests, backups/restore drill, documentation, certification dossier, Modelo 24, conformity tests | **AT certificate obtained** |
| **7. Pilot & launch** | Pilot with 1–3 real companies, data migration tools, billing/subscriptions, onboarding | First paying customers |
| **8. After v1** | CIUS-PT (B2G), quotes/proformas/orders, POS, lots and traceability, integrations, MCP | — |

Note: phases 2–4 are the certification-critical path; start the qualified seal provider and AT test environment access **early in phase 2** since both involve external lead times.

---

## 13. Testing strategy

| Level | What | Tools |
|---|---|---|
| Domain unit tests | PriceCalculator, rounding, NIF validation, series rules, chronology, status transitions | PHPUnit |
| Golden files | Known document sequences → exact expected hashes, ATCUD, QR payloads | PHPUnit + fixtures |
| Pricing vectors | Shared `pricing-test-vectors.json` → exact line amounts, VAT summary and totals for net/gross mode × per-line/per-group rounding | PHPUnit, Vitest, shop test suites |
| Company isolation | With `app.company_id` set to company A, every read/write attempt on company B's rows must fail; every company table has an RLS policy | Integration tests + CI schema check |
| Concurrency | Parallel issuance on the same series → no gaps, no duplicates, chain intact | PHPUnit + parallel processes against real PostgreSQL |
| DB integrity | Attempts to UPDATE/DELETE fiscal rows as the app role must fail | Integration tests |
| SAF-T | Generated files validate against the official XSD; control totals match | CI job |
| AT contract | Series, invoice and transport webservices against the AT test environment | Scheduled CI job |
| API | Request/response contracts, permissions, company isolation via API | PHPUnit functional tests |
| E2E | Login → create customer → issue invoice → receipt → PDF; GR → invoice | Playwright |

---

## 14. Open decisions by phase **[DECIDE]**

Each decision has a recommendation; confirming the recommendation is enough to proceed. Only the first group blocks the start of implementation.

### 14.1 Before starting (Phase 0) — decided

| # | Decision | Outcome |
|---|---|---|
| 1 | Previous employment contract | Non-compete expired; everything built from scratch |
| 2 | Product name | Pending. Code uses Symfony's default `App\` namespace and a neutral repo codename, so the brand can be chosen later without code changes |
| 3 | OpenAPI tooling | NelmioApiDocBundle + DTOs |
| 4 | Frontend router | TanStack Router |
| 5 | Versions and tooling | PHP 8.4, Symfony 7.4 LTS, PostgreSQL 17+, Node LTS, pnpm |

### 14.2 Before the fiscal core (Phase 2)

| # | Decision | Recommendation |
|---|---|---|
| 6 | Unit price and quantity decimals | Store 6, display 2 (more only when needed) |
| 7 | Rounding mode | HALF_UP **[VERIFY]** |
| 8 | Default rounding method | Per rate group; per line available per company and per document |
| 9 | Discount forms | Percentage + cascading percentages + fixed amount per line; global percentage |
| 10 | Series convention | One series per document type per year (e.g. `FT 2027A`), more series allowed |
| 11 | Currency | EUR only in v1 (schema already has currency fields) |
| 12 | Tax regions | Mainland, Azores and Madeira supported from v1 (data-driven, low cost) |
| 13 | Working document types | OR, PF, NE in v1 |
| 14 | Order stock reservation | Informational "committed" quantity, no hard reservation |

### 14.3 Start early (external lead times)

| # | Decision / action | Recommendation |
|---|---|---|
| 15 | Qualified trust service provider for the electronic seal | Shortlist 2–3 Portuguese providers with remote sealing APIs; compare API, price per seal, SLA |
| 16 | AT webservices test environment access and certificates | Request during Phase 1 |
| 17 | Software producer registration / certification requirements | Confirm the requirements for submitting Modelo 24 as TCWeb **[VERIFY]** |

### 14.4 Before output, stock and launch (Phases 3–7)

| # | Decision | Recommendation |
|---|---|---|
| 18 | PDF engine (on-demand rendering, §7.8) | PHP library (mPDF) vs Twig → Gotenberg; decide with a quick spike rendering the invoice template in both |
| 19 | Negative stock | Company setting, default: warn but allow |
| 20 | Stock valuation | Weighted average cost |
| 21 | Nested kits | Not in v1 |
| 22 | Annual inventory communication | Include if pilot clients are obliged **[VERIFY]** |
| 23 | Hosting (EU) | Decide before Phase 6; containers + managed PostgreSQL |
| 24 | Pricing and plans | Decide before Phase 7 |
| 25 | Product brand and visual identity | Before Phase 7 (pilot can use working name) |

---

## 15. Legal references to keep at hand

- Portaria n.º 363/2010 (certification of invoicing software) and later amendments.
- Despacho n.º 8632/2014 (technical requirements for invoicing software).
- Decreto-Lei n.º 28/2019 (invoicing, archiving, electronic invoices).
- Portaria defining ATCUD and QR code requirements, and the AT's QR code technical specification.
- SAF-T (PT) structure, technical notes and XSD (Portal das Finanças).
- AT webservice technical documentation: series, invoice communication, transport documents.
- State Budget provisions on qualified electronic signatures (mandatory from 1 January 2027) and CIUS-PT for B2G.

All **[VERIFY]** items must be checked against the current versions of these documents, which change frequently.
