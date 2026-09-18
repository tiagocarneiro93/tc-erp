# Phase 2 — Fiscal core

Scope per `docs/PLAN.md`: §6.6–6.9, §7.1–7.4, §7.6, §7.9 of `docs/technical-scope.md`.

**Prerequisite status:** no longer blocking. Despacho 8632/2014, Portaria
363/2010 (Hash signing string, Art. 6.º), Portaria 195/2020 (ATCUD), the AT's
QR code spec, and the SAF-T 1.04_01 XSD are all in `docs/legal/` — see its
README for what each resolves and citations used below. `saft-pt-structure.pdf`/
`saft-pt-technical-notes.pdf` (the prose SAF-T spec) are still missing, but the
XSD covers every structural `[VERIFY]` this phase needs; fetch the prose docs
opportunistically during the phase, not blocking any task. Scope §14.2
decisions are confirmed (rounding: HALF_UP; series: corrected per
`docs/decisions/0005`, see below).

Two items remain genuinely open and are called out per-task below rather than
blocking the whole phase: the exact legal conditions for cancelling an
already-issued document (§7.4, beyond the ordering rules Despacho 8632/2014
does give — task 2.10), and the AT series-communication webservice itself
(task 2.2 uses a manually-entered validation code; the live call is Phase 3).

Depends on Phase 1 being complete: customers/suppliers, products (with tax
rate + exemption reason), product families, price lists, warehouses, and the
two 🧑 Phase 1 exit items (mainland/PT-AC/PT-MA tax-rate sign-off, owner
review) closed.

Every new company-scoped write endpoint is permission-gated the same way
Phase 1 tasks are — `PermissionChecker::isGranted()` against
`docs/decisions/0002-roles-and-permissions.md`'s list. This phase introduces
new permission scopes it doesn't yet have a name for (issuing, cancelling,
managing series) — task 2.2 proposes the additions to ADR 0002.

## Decisions this plan makes (owner: confirm or override before coding)

1. **Module placement.** `Series`, `Document`/`DocumentLine`/`DocumentTaxSummary`/
   `DocumentReference`/`DocumentStatusEvent`, `Receipt`/`ReceiptAllocation`,
   `DocumentDraft`, and `DocumentSigner` all live in **Fiscal** (§4.1 already
   assigns document types there; the rest of the fiscal-document family
   belongs next to it). `PriceCalculator` extends Phase 1's
   `Tax\Domain\VatConversion` (docs/plans/phase-1.md decision 2) in place,
   inside **Tax** — one calculator, not a second implementation elsewhere,
   per §7.9.2 principle 1. Fiscal depends on Tax's calculator the same way
   Catalog already depends on Tax's rate-resolution port (Phase 1 task
   1.6/1.7's cross-module pattern, ADR 0004).
2. **Series scoped to one document type, no forced year rotation** —
   `docs/decisions/0005-series-scoped-to-one-document-type-no-year-limit.md`.
   The `code` field is free text the company chooses (e.g. `2026A`, or just
   `A` for a series meant to run indefinitely); nothing in the schema or the
   application enforces a year in it.
3. **New permissions** (extending ADR 0002): `documents.issue`,
   `documents.read`, `documents.cancel`, `series.manage`. Receipts and
   working documents reuse `documents.issue`/`documents.read` rather than
   getting their own scope — they're the same "can this person create
   fiscally relevant records" concern, and splitting it finer isn't asked
   for anywhere in the scope.
4. **`PriceCalculator` precision tiers** (§7.9): quantities and unit prices
   stored/calculated at 6 decimals (`brick/math` `BigDecimal`), line and
   document totals rounded to 2 decimals only at the boundary where they're
   persisted to `documents`/`document_lines` — never rounded mid-calculation.
   This is what §7.9's "more decimals internally, 2 for money" already
   specifies; stated here because task 2.1 is test-first and needs the exact
   rule before the first test is written.
5. **Golden-file fixtures** for the signing/QR tests (task 2.5) are built
   from the worked examples in `docs/legal/at-qrcode-spec.pdf` §5 (Fatura,
   Fatura simplificada, Fatura pró-forma, Guia de transporte) — known-input,
   known-output pairs taken directly from the AT's own document, not
   invented. The Hash itself can't be golden-tested against the AT's
   examples (those don't include a private key), so Hash tests are
   round-trip (sign, then verify with the paired public key) plus a
   deterministic-output test as a regression guard.
6. **`DocumentSigner`'s dev key** reuses Phase 0's pattern for secrets never
   committed: generated locally by a Makefile target, git-ignored, read from
   an env var — the same shape as any other dev-only credential already in
   this repo, no new pattern needed.

---

### 2.1 `PriceCalculator`

- Extends `Tax\Domain\VatConversion` (Phase 1) into the full calculator
  (§7.9.1–7.9.8): net and gross entry modes; per-line and cascading
  percentage discounts; fixed-amount line discounts; global document-level
  discount allocated across lines by largest remainder (§7.9.4); VAT always
  computed last, per tax-rate group; rounding mode HALF_UP (decision
  confirmed, `docs/technical-scope.md` §14.2); `per_line`/`per_group`
  rounding method, company-level default with a per-document override
  (§7.9.5).
- `pricing-test-vectors.json` (`api/tests/Fixtures/`): hand-built vectors
  covering each discount combination, a mixed-tax-rate document, and at
  least one PT-AC/PT-MA case exercising the region-specific rates seeded in
  Phase 1. New or changed vectors need 🧑 owner review before merge
  (CLAUDE.md testing rule) — flag this explicitly in the PR/commit, don't
  assume silent approval.
- `POST /companies/{c}/calculate`: takes the same line shape a draft would,
  returns the canonical calculation — this is what the web document editor
  calls live and what `Drafts` (task 2.4) and issuance (task 2.6) both use
  internally, so there is exactly one code path from raw lines to totals
  anywhere in the system (no controller, Twig, or frontend computes a total
  itself — CLAUDE.md hard rule).

**Accept:** all vectors in `pricing-test-vectors.json` pass; a property-style
test confirms line amounts always sum to the document total (no silent
cent drift from rounding); `/calculate` and the internal calculator produce
identical output for the same input (no second implementation drift).

### 2.2 Series

- `series` per §6.6's existing schema (`id, company_id, document_type, code,
  is_training, validation_code, status, first_number, last_number,
  last_hash, last_issue_date, last_system_entry_at, at_communicated_at,
  at_finished_at`, `UNIQUE (company_id, document_type, code)`) — the schema
  already has no year column (decision 2 above needed no schema change,
  only correcting the convention text in §14.2).
- Lifecycle per §7.6: `draft` → `active` (once a `validation_code` exists —
  entered manually in this phase, see prerequisite note above; the AT
  webservice call that would populate it automatically is Phase 3) →
  `finished`/`cancelled`. A series with no `validation_code` cannot issue.
- Training series (`is_training`): same lifecycle, flagged so issued
  documents print the training mention (§7.2 — exact wording still
  `[VERIFY]`; use a clearly-marked placeholder and flag it for the owner
  rather than guessing wording, per CLAUDE.md).
- CRUD + list; `series.manage` permission (decision 3).
- ADR 0002 updated with the four new permissions from decision 3.

**Accept:** a series cannot transition to `active` without a
`validation_code`; `UNIQUE (company_id, document_type, code)` enforced and
isolation-tested; unit tests for every lifecycle transition, including the
illegal ones (e.g. issuing against a `finished` series) being rejected.

### 2.3 Fiscal-table immutability

- Grants + triggers (§6.9) on `documents`, `document_lines`,
  `document_tax_summary`, `document_references`, `document_status_events`,
  `receipts`, `receipt_allocations`: `app_runtime` gets `INSERT`/`SELECT`
  only (no `UPDATE`/`DELETE`), enforced at the database level, not just in
  application code — CLAUDE.md's "never grant the runtime role more
  privileges to work around it" applies literally here. `documents.status`
  and the AT-communication columns are the sole allowed-mutable exception
  (§6.9), via a trigger that whitelists exactly those columns.
- **Customer/Product immutability once referenced by an issued document**
  (Despacho 8632/2014 §3.3.3–3.3.5, found while unblocking this phase, not
  in the original Phase 1 scope): once a customer has at least one issued
  document, `Customer::update()` must reject changing `nif` or `name`,
  except filling a previously-blank NIF or replacing the generic
  `999999990` with a real one; a product's `description` locks the same way
  once referenced by an issued document. Implemented as a check in
  `UpdateCustomerHandler`/`UpdateProductHandler` (a
  `CustomerHasIssuedDocuments`/`ProductHasIssuedDocuments` port into Fiscal,
  ADR 0004's cross-module pattern) — not in the entities themselves, since
  `Customer`/`Product` in Parties/Catalog have no way to know about Fiscal's
  tables without violating Deptrac.
- DB integrity tests: attempt `UPDATE`/`DELETE` on each fiscal table as
  `app_runtime` and assert it fails; attempt updating a disallowed column
  via the allowed-columns trigger and assert it fails while the allowed ones
  succeed.

**Accept:** every fiscal table from this list rejects `UPDATE`/`DELETE` as
`app_runtime` except the whitelisted status/communication columns; a
customer/product with an issued document rejects a NIF/name/description
change via the API (422) with the two documented exceptions still working;
isolation tests for the new company-scoped tables.

### 2.4 Drafts

- `document_drafts` (§6.6: mutable, `payload JSONB`, `calculated JSONB`) —
  create/update/delete, never printable or sendable (CLAUDE.md hard rule:
  "drafts are not documents"), UI shows an on-screen preview only.
- `calculate` action re-runs task 2.1's calculator and stores the result in
  `calculated`, live, on every line change.
- Validation before a draft can be issued (task 2.6 reuses this): customer
  resolvable, at least one line, exemption reason present on any 0%-rate
  line, `references` present for NC/ND, series matches the draft's
  `document_type`.

**Accept:** a draft's `calculated` field always matches what `/calculate`
would return for its current `payload`; attempting to print or email a
draft is rejected at the API (not just hidden in the UI); validation errors
are per-field and surfaced before issuance is attempted.

### 2.5 `DocumentSigner`

- Port + OpenSSL/RSA adapter (dev key generated locally, git-ignored,
  decision 6). Signing string exactly per **Portaria 363/2010, Art. 6.º**:
  `InvoiceDate;SystemEntryDate;InvoiceNo;GrossTotal;PreviousHash`, `;`-joined,
  RSA-signed, base64-encoded — no longer `[VERIFY]`, resolved from the
  primary source (see `docs/legal/README.md`). Chaining: previous document
  in the same series (task 2.2), full stop — no year-boundary special case
  (ADR 0005). First document in a series signs with an empty previous-hash
  (Despacho §2.1.5).
- Printed mention: characters at positions 1, 11, 21, 31 of the hash,
  hyphen-joined, followed by "Processado por programa certificado n.º
  XXXX/AT" (Despacho §2.2.2) — the certificate number itself is a dev
  placeholder until the owner completes AT software certification (still
  open, scope §14.3 item 17, tracked separately from this phase's blockers).
- **ATCUD builder**: `{validation_code}-{sequential_number}` (Portaria
  195/2020 Art. 3).
- **QR payload builder**: every field A–S from `docs/legal/at-qrcode-spec.pdf`
  §4, built directly off a `Document`'s stored totals/tax summary — never
  recomputed from lines at print time, since the document is immutable and
  already has canonical totals.
- Golden-file tests (decision 5) using the QR spec's own four worked
  examples; round-trip sign/verify tests for the Hash.

**Accept:** the four QR golden-file cases byte-match `docs/legal/at-qrcode-spec.pdf`
§5's example messages; a resigned document (same inputs) always produces
the same Hash; chain verification (each document's `PreviousHash` matches
the prior document's `Hash`) is a standalone test, not just implied by the
signing test.

### 2.6 Issuance use case

- `POST /companies/{c}/documents/drafts/{id}/issue`, `Idempotency-Key`
  required, exactly the 15-step transaction in §7.1: idempotency check,
  draft validation, series lock (`SELECT ... FOR UPDATE`), chronology
  checks, number assignment, canonical calculation (task 2.1), signing
  (task 2.5), ATCUD/QR, inserts (`documents`, `document_lines`,
  `document_tax_summary`, `document_status_events`), series update, audit
  log, idempotency result stored, draft deleted, commit.
- `customer_snapshot`/`issuer_snapshot` captured at issuance (§6.6) so a
  later change to either never alters an already-issued document.
- Side effects listed in §7.1 step 12 (stock movements, account entry,
  `at_communications` row) are stubbed/no-op in this phase where their
  owning module doesn't exist yet (stock: Phase 5; AT communication: Phase
  3) — the `at_communications` row is still written as `pending` so Phase
  3 has nothing to backfill, per the outbox pattern already designed in
  §7.5.
- Concurrency test: parallel issuance against the same series produces no
  gaps, no duplicate numbers, and a valid unbroken hash chain — this is the
  test family CLAUDE.md specifically calls out as required.

**Accept:** the full transaction is exercised end to end (draft → issued
document) in a functional test; the concurrency test (parallel requests,
same series) passes reliably (run it more than once in CI, not as a single
lucky pass); replaying the same `Idempotency-Key` returns the original
document, never a second one; no code path outside this use case can insert
into `documents`.

### 2.7 FT, FS, FR, NC, ND

- The four the SAF-T XSD confirms sit under `InvoiceType`
  (`docs/legal/SAFTPT1.04_01.xsd`) plus FS: normal issuance via task 2.6.
- `document_references` (§6.6) links NC/ND to the invoice(s) they correct;
  `POST /documents/{id}/credit-note` prefills a draft from an existing
  document's lines (§7.4).
- Despacho 8632/2014 §3.3.7–3.3.8 (found while unblocking this phase): a
  credit note cannot be issued against a document that is already cancelled
  or already fully rectified; a document cannot be cancelled (task 2.10) if
  it already has a rectifying NC/ND without cancelling that one first.
  Enforced in the credit-note/cancel handlers, not just documented.

**Accept:** each of the five types issues correctly through task 2.6; a
credit note against an already-fully-rectified document is rejected (422)
with a citation-bearing error; NC/ND correctly reduce/increase whatever
running "open amount" concept the current phase tracks for the referenced
document (minimal — full current-accounts logic is §6.7, later).

### 2.8 Working documents (OR, PF, NE)

- §6.8: same issuance mechanics as task 2.6 but no fiscal signing
  requirement in the same way (`document_types.signed` already governs
  this per Phase 1's seed) — confirm against Despacho §1.1/§1.2 whether OR/PF/NE
  need the "Este documento não serve de fatura" mention (§1.2 says yes for
  anything in SAF-T tables 4.2–4.4 without being an invoice) and print it.
- Conversions: full and partial conversion of a working document into an
  invoice, tracking pending quantities per line so a partially-converted
  OR/PF/NE still shows what's left to invoice.

**Accept:** converting a working document fully closes it (no pending
quantity left); converting partially leaves the correct remainder and the
document stays open; the "não serve de fatura" mention is present on every
working-document PDF/preview (preview only — full PDF rendering is Phase 3,
so this is checked on the on-screen preview data for now).

### 2.9 Receipts (RG)

- **Not a variant of the invoice path.** Per the SAF-T XSD, receipts are
  `Payments/Payment`, not `SalesInvoices/Invoice` — RG isn't a valid
  `InvoiceType` value at all (`docs/legal/SAFTPT1.04_01.xsd`). §6.6's
  `receipts`/`receipt_allocations` tables already reflect this split; build
  their own issuance path (still going through task 2.5's `DocumentSigner`
  port for the ATCUD, but unsigned — Despacho §2.2.3 — printing "Emitido
  por programa certificado n.º XXXX/AT" instead of the 4-char hash variant).
- `receipt_allocations`: a receipt allocates its total across one or more
  open invoices (`document_id`, `amount`, `settlement_amount`) — no
  allocation exceeding an invoice's open amount.

**Accept:** a receipt issues with an ATCUD but no Hash/4-char print mention;
allocating more than an invoice's open balance is rejected; the concurrency
test family from task 2.6 (no gaps/duplicates) applies to receipt numbering
too, on its own series.

### 2.10 Cancellation

- Status `A` (§7.4). Legal conditions beyond the ordering rules already
  resolved (task 2.7's §3.3.7–3.3.8) are still `[VERIFY]` — `dl-28-2019.pdf`
  is now in `docs/legal/` but does **not** resolve this: it only requires
  that cancelled documents be logged (Art. 7.º §5), not what makes a
  cancellation legal in the first place. The substantive rule most likely
  lives in **CIVA Art. 29.º §7** itself (cited by Despacho 8632/2014
  §2.2.6, but not reproduced in DL 28/2019's text since that decree only
  redlines the paragraphs it actually changes). Until CIVA's own text is
  obtained: cancellation is only allowed before the document has been
  communicated to the AT (`at_communications.status` still `pending`,
  never `sent`/`accepted`) — the one condition we can state with
  confidence from what's already in `docs/legal/` (Despacho's ordering
  rules presuppose a pre-communication window) — and this restriction is
  flagged in code and in this file as provisional pending CIVA Art. 29.º.
- Writes a `document_status_events` row; reverses stock/account effects
  with new compensating entries (never deleting) — stubbed the same way
  task 2.6 stubs them, real wiring in Phase 5.

**Accept:** cancelling a document with a rectifying NC/ND already issued
against it is rejected (reuses task 2.7's check); cancelling a
communicated-to-AT document is rejected under the provisional rule above;
a cancelled document's data is untouched (only a new status event and the
allowed `status` column change) — verified against task 2.3's immutability
triggers, not just application logic.

### 2.11 Web: document editor and related screens

- Document editor: keyboard-friendly line entry, net/gross mode switch,
  totals from live calls to task 2.1's `/calculate` (never computed in the
  browser — CLAUDE.md hard rule).
- Document list + detail (read-only once issued, obviously).
- Conversion and credit-note actions (tasks 2.7/2.8) surfaced from a
  document's detail view.
- Receipts: a screen to create one against one or more open invoices with
  the allocation UI from task 2.9.
- On-screen draft preview — explicitly not a printable/downloadable
  document (no PDF here; that's Phase 3's `DocumentPdfRenderer`, §7.8).

**Accept:** Playwright e2e — create a draft, see live totals update as
lines change, issue it, see it appear read-only in the document list,
issue a credit note against it, create a partial-conversion working
document and convert it, issue a receipt allocated across two invoices.

---

## Phase 2 exit criteria

- All fiscal test families green: golden-file (Hash chain, ATCUD, QR),
  concurrency (parallel issuance, no gaps/duplicates/broken chains), DB
  integrity (UPDATE/DELETE rejected on every fiscal table except the
  whitelisted columns), pricing test vectors, company isolation, API
  functional tests, Playwright e2e.
- No document can be modified after issuance by any path, including a
  direct DB session as `app_runtime` (task 2.3's whole point).
- `make openapi` run; spec and web client regenerated and committed.
- `make lint` and `make test` green on every task, not just at the end.
- 🧑 Owner review of the signing and calculation code (`DocumentSigner`,
  `PriceCalculator`) before Phase 3 starts — CLAUDE.md's standing rule for
  anything touching signing or calculation.
- 🧑 Owner reviews `pricing-test-vectors.json` (decision/task 2.1) before
  the calculator is considered done, not only at phase end.
- 🧑 Owner confirms the provisional cancellation-window rule (task 2.10) or
  supplies CIVA Art. 29.º's own text (DL 28/2019 doesn't reproduce it) so
  it can be replaced with the actual legal conditions before Phase 3 (AT
  communication) makes the distinction between "communicated" and "not
  yet communicated" load-bearing.
