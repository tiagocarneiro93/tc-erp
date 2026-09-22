# Phase 3 — Output and AT compliance

Scope per `docs/PLAN.md`: `docs/technical-scope.md` §5.4 (async work), §6.12 (AT
outbox/print/storage schema), §7.5 (AT communication), §7.6 (series
lifecycle — the part left open by Phase 2), §7.7 (SAF-T export), §7.8 (PDF,
qualified seal, email), §4.1 (module list — this phase is the first to touch
the `AtIntegration` and `Output` modules).

## Prerequisite status

No longer blocking:
- **AT test webservice access**: `TesteWebservices.pfx` (mutual-TLS client
  certificate) and `Chave Cifra Publica AT 2027.cer`/`.p7b` (AT's public key
  for the SOAP-header password cipher), obtained from asi-cd@at.gov.pt.
  Verified by byte inspection (subject/validity match the manuals), not just
  trusted. See `docs/legal/README.md`.
- **AT sub-user login credentials**: created by the owner at acesso.gov.pt
  with the `WFA` (e-Fatura), `WSE` (series) and `WDT` (transport, Phase 4)
  profiles assigned, confirmed by screenshot. Entered through the app's own
  Settings screen (`AtCredentialsForm.tsx`, task 1.4) — never seen by Claude
  Code, never in this repo. `TestAtCredentialsHandler` today only proves the
  encrypt/store/decrypt round-trip, not a real AT call — that's this phase's
  job.
- **AT webservice manuals and WSDLs**: e-Fatura (generic + specific, v2.0/v3.0)
  and series communication (generic + specific, v1.2), both with their
  WSDLs (`Fatcorews.wsdl`, `SeriesWS.wsdl`), plus the invoice-lookup WSDL
  (`FatshareInvoices.wsdl` — not required by §7.5, see task 3.2's note) all
  in `docs/legal/`. Transport documents (`at-ws-transport.pdf`,
  `DocumentosTransporte.wsdl`) are also in, ahead of Phase 4.

Still open, not blocking the plan but blocking part of the work:
- 🧑 **Qualified trust service provider** for the electronic seal
  (`docs/technical-scope.md` §14.3 item 15) — not yet shortlisted. Blocks
  only `ElectronicSealer`'s real adapter (task 3.6); the fake adapter every
  other task needs to keep moving doesn't depend on it.
- `oe-2026-extract.pdf`, `eidas-910-2014.pdf`, `etsi-en-319-142.pdf` and the
  eventual provider's own API docs are still unobtained (`docs/legal/README.md`).
  Fetch opportunistically once a provider exists — task 3.6 is the only task
  that needs them, and even then only for the real adapter, not the fake one.
- Software producer registration / certification requirements (§14.3 item 17,
  still `[VERIFY]`) — needed before going live, not before Phase 3's own exit
  criterion (AT **test** environment, sealed PDFs in **sandbox**).
- `[VERIFY]` items inside §7.7/§7.8 that don't yet have an answer in
  `docs/legal/`: SAF-T's "full billing export" vs "monthly communication
  export" content difference (task 3.3); which master-data rows a period
  export includes (task 3.3); exact copy/reprint mention rules (task 3.5);
  whether a TCWeb certificate can seal on behalf of client companies (task
  3.6 — a provider/contractual question, not resolvable from a document).
  One is already resolved and doesn't need re-flagging: DL 28/2019 Chapter V
  (Arts. 19–30) already answers "archiving obligations for electronic
  invoices" — 10 years, originals and backups in physically/logically
  distinct locations (Art. 27.º §2) — see `docs/legal/README.md`'s
  `dl-28-2019.pdf` row.

## Decisions this plan makes (owner: confirm or override before coding)

1. **New modules, per §4.1's table** (not a new decision, just first use):
   `AtIntegration` (webservice clients, outbox processing, communication
   status) and `Output` (PDF rendering, seal, email, archive). Each needs its
   own four Deptrac layers + ruleset entries (`deptrac.yaml`'s own comment:
   "a brand new module needs its own four layers... copying the Platform
   block"). SAF-T export stays in **Fiscal** per §4.1's own assignment
   ("...SAF-T export — the strict core"), not a new module, and not
   `AtIntegration` either — SAF-T is a file export, not a webservice call.
   Series stays in **Fiscal**, unchanged from Phase 2 decision 1 (§4.1's
   table nominally puts "series management" under **Company**, but Phase 2
   already deliberately diverged from that and documented why).
2. **Cross-module data access for `AtIntegration`**: the webservice consumer
   needs document/receipt data shaped for `RegisterInvoice`/`RegisterWork`/
   `RegisterPayment` bodies, which lives in **Fiscal**
   (`IssuedDocumentReader`), and the decrypted AT sub-user credentials, which
   live in **Company** (`AtCredentialsRepository`/`AtCredentialsEncryptor`).
   Neither module's repositories/entities are used directly — `AtIntegration`
   depends on new read-only application-service ports each module exposes
   (ADR 0004's already-established pattern, e.g. Catalog → Tax), not a new
   architecture.
3. **Series registration is synchronous; per-document communication stays
   async.** `at_communications.kind`'s schema comment (§6.12) lists
   `series_register|series_finish|invoice|transport` together, which could
   read as "all four go through the same async outbox+sweeper." Recommend
   against that for series specifically: `Series::activate()` (task 2.2)
   currently takes a `validationCode` as a direct parameter and sets
   `active` immediately — making registration async would need a new
   intermediate status (registration requested, code not back yet) for a
   low-volume, deliberately user-initiated action where waiting a few
   seconds for AT's synchronous SOAP response is simpler and gives
   immediate feedback in the Séries screen. `series_register`/
   `series_finish` still get an `at_communications` row each — for the
   audit trail §6.12 clearly wants — but written with `status` already
   resolved (`accepted`/`rejected`) from the synchronous call, never left
   `pending` for a sweeper to find. Per-document invoice/work/payment
   communication (already enqueued `pending` by task 2.6/2.10, one row per
   document) stays exactly as designed: async, sweeper-driven, retried —
   real fire-and-forget volume, unlike a handful of series a company sets
   up once.
4. **SOAP transport**: PHP's native `ext-soap` `SoapClient`, built from the
   WSDLs now vendored in `docs/legal/` (official, stable, already schema-
   validated by AT itself), with a custom `SoapHeader` for the WS-Security
   block and a stream context supplying `TesteWebservices.pfx` for mutual
   TLS — rather than hand-rolling XML envelopes. One `AtSoapClientFactory`
   (or similar) per WSDL, shared by every operation on that service.
5. **WS-Security cipher** (`docs/legal/at-ws-series-aspetos-genericos.pdf`
   §4.1, byte-identical requirement in the e-Fatura manual): implemented as
   its own small, independently tested primitive — a random 128-bit AES key
   `Ks` per request; `Nonce = Base64(RSA-encrypt(Ks, AT's public key))`;
   `Password`/`Created` = `Base64(AES-128-ECB-PKCS5(Ks, value))`. Lives in
   `AtIntegration` (only its webservice client needs it), taking the
   decrypted subuser/password via the Company port from decision 2 — it
   never touches `Company`'s own encryption-at-rest scheme, a separate
   concern.
6. **`SoftwareCertificateNumber`/`numCertSWFatur` = `0`** on every call,
   both webservices — not a guess: both WSDLs literally define `0` as what
   an uncertified producer sends (`Fatcorews.wsdl`'s
   `nonNegativeInteger`, `SeriesWS.wsdl`'s explicit doc comment "Se não
   aplicável, deve ser preenchido com '0' (zero)"). Revisit once software
   certification (§14.3 item 17) is actually granted a real number.
7. **PDF engine** (§14.3 item 18, still `[DECIDE]`): task 3.5 spikes both
   mPDF and Twig → Gotenberg against the invoice template, 🧑 owner picks —
   same "spike, then ask" treatment Phase 2 used for the QR/Hash goldens.
8. **Real gap found while planning, fixed as part of task 3.2**:
   `IssueReceiptHandler` (task 2.9) never calls `AtCommunicationQueue::enqueue()`
   — receipts issue today without ever entering the AT outbox at all, despite
   `Fatcorews.wsdl` having a `RegisterPayment` operation for exactly this.
   Added alongside the consumer, not as a separate task, since it's a one-line
   fix to an existing handler.
9. **Series-code validation, carried over from Phase 2** (already catalogued
   in `docs/PLAN.md`'s Phase 3 scope and `docs/legal/README.md`): implement
   `at-ws-series-aspetos-especificos.pdf` §1.3.2's construction rules (≤35
   chars, `[A-Za-z0-9._-]` only, no leading/trailing/doubled separator,
   can't start with `AT`) in `Series`/`CreateSeriesRequest`, as part of task
   3.1, before wiring `registarSerie` — a series created earlier with an
   invalid code would otherwise only fail once it actually hits AT.
10. **Verification limits, same shape as task 2.5's Hash tests**: nothing
    here can be golden-tested against a byte-exact AT-produced example — we
    have no AT private key to decrypt a Nonce we didn't generate, and no
    "known good" SOAP response captured yet. Unit tests cover field
    formats/lengths and a self-contained round-trip (encrypt then decrypt
    with our own copy of the flow); the real proof is a live call against
    AT's **test** environment (e.g. `registarSerie` for a throwaway test
    series) as each task's acceptance criterion, not simulated.

---

### 3.1 AT webservice client foundation + series communication

- `AtIntegration` module scaffolding (decision 1): four Deptrac layers,
  ruleset entries, directory structure matching every other module.
- `AtSoapClient` port + `ext-soap` adapter (decision 4): WSDL-driven client,
  WS-Security `SoapHeader` builder implementing the cipher (decision 5),
  stream context wired to `TesteWebservices.pfx` for mutual TLS. Test vs.
  production endpoint chosen by environment config (test: port 722 for
  series per `at-ws-series-aspetos-especificos.pdf` §1.2.2, port 723 for
  e-Fatura per the generic manual).
- Series-code validation (decision 9) added to `Series`/`CreateSeriesRequest`.
- `registarSerie`/`finalizarSerie`/`anularSerie`/`consultarSeries` wired into
  `Series`'s lifecycle (decision 3, synchronous): `activate()` no longer
  takes a manually-entered `validationCode` — it calls AT directly and uses
  `codValidacaoSerie` from the response; `finish()` calls `finalizarSerie`
  with `seqUltimoDocEmitido` (the series' own `lastNumber`); a new `cancel()`
  path for an `active` series not yet used calls `anularSerie`
  (`declaracaoNaoEmissao: true`, per the WSDL's own required confirmation
  field — only legal the same day or the day after registration, per
  §1.3.3). Each call writes an `at_communications` row with `kind:
  series_register`/`series_finish` and the resolved status (decision 3).
- `numCertSWFatur: 0` (decision 6).
- `meioProcessamento: 'PI'` (Programa Informático de Faturação — the only
  value that describes this system, per `at-ws-series-aspetos-especificos.pdf`
  §1.3.9's three-value table).

**Accept:** a series activated through the real test webservice gets back
and stores an actual AT-issued `codValidacaoSerie` (verified against AT's
test environment, decision 10 — not simulated); a series code violating
§1.3.2's rules is rejected before any AT call is attempted (422, citing the
rule); finishing/cancelling a series calls the matching operation and
updates local state only on AT's success; the WS-Security cipher has unit
tests for field shapes plus a round-trip self-test (decision 10);
`at_communications` gets a row for every series register/finish/cancel
attempt, successful or not.

### 3.2 Invoice/receipt AT communication (outbox consumer)

- Fixes decision 8 (`IssueReceiptHandler` never enqueues) alongside building
  the consumer — every issuance path feeds the same outbox before this task
  is done.
- `CommunicateToAt(companyId, communicationId)` Messenger handler (§7.5):
  loads the `at_communications` row plus whatever document/receipt data it
  points to (decision 2's Fiscal-exposed port), builds the right SOAP body
  (`RegisterInvoice` for FT/FS/FR/NC/ND, `RegisterWork` for OR/PF/NE,
  `RegisterPayment` for RG — resolved from the subject's own
  `document_type`/`saft_section`, not from `kind`, since §6.12's `kind` enum
  has no `work`/`payment` value — `invoice` covers the whole e-Fatura-family
  communication regardless of which of the three operations it resolves to),
  calls AT, records `response_code`/`response_message`/`at_reference`, moves
  `status` to `accepted`/`rejected`/`failed` per §7.5's rules (permanent
  validation errors → `rejected`; transient/technical errors → retry).
- Scheduled sweeper (§5.4): the `SECURITY DEFINER` function returning
  `(company_id, item_id)` pairs for `pending`/`failed` rows past
  `next_attempt_at`, then each item processed inside that company's own RLS
  context — not a cross-company query as any runtime role. Exponential
  backoff between retries.
- `ChangeInvoiceStatus`/`DeleteInvoice`/equivalent Work/Payment operations:
  wired for whatever local state transitions actually produce one — today
  that's cancellation (task 2.10) reaching `A`. Since task 2.10 already
  blocks cancelling a document once `at_communications.status` is
  `sending`/`accepted`, a `ChangeInvoiceStatus` call from this app's own
  cancel flow can in practice only ever apply to a document AT never
  confirmed receiving — noted rather than treated as dead code, since a
  `pending`/`failed`/`rejected` communication can still get its status
  changed downstream by other means (e.g. a manual AT-side correction) that
  this system doesn't need to replicate in v1.
- AT status per document surfaced via the existing `GET .../documents/{id}`
  read model (task 2.11 already returns document-level fields; add the
  `at_communications` summary alongside `can_cancel`/`can_credit_note`/
  `convert_targets`).

**Accept:** issuing any of FT/FS/FR/NC/ND/OR/PF/NE/RG results in exactly one
`at_communications` row that the consumer picks up and sends to AT's test
environment, verified as actually accepted there (decision 10); a
deliberately malformed request (e.g. missing required field) comes back
`rejected` with AT's own message stored, not retried forever; killing the
worker mid-processing and restarting it doesn't lose or duplicate the row
(the sweeper's safety-net role, tested directly); the receipt-enqueue gap
(decision 8) has a regression test so it can't silently regress again.

### 3.3 SAF-T (PT) export + XSD validation

- Streaming `XMLWriter` generator (§7.7) — no full document tree in memory,
  same principle CLAUDE.md's testing rules already expect elsewhere (e.g.
  cursor pagination, task 2.11's own docblock naming this exact technique
  as a future candidate).
- Full-period export type built first (its master-data-inclusion rule is the
  same `[VERIFY]` either way); the monthly-communication-export type's
  actual content difference is still open — resolve from `docs/legal/`
  opportunistically before this task is called done, or stop and ask if
  nothing in the existing sources answers it plainly (CLAUDE.md: never
  guess an AT technical format).
- Validated against `SAFTPT1.04_01.xsd` (already in `docs/legal/`) both in
  the application (before offering the file for download) and in CI against
  fixture data generated from real issued documents in the test suite —
  not a hand-written XML fixture that could drift from what the generator
  actually produces.
- File goes to `stored_files` (task 3.4) once that exists; this task can
  build the generator and validate its output before 3.4 lands, wiring
  storage last.

**Accept:** a generated SAF-T file validates against the XSD for a company
with a representative mix of document types (invoice, credit note, working
document, receipt); the generator is exercised against real issued
documents (task 2.6–2.9's own test fixtures), not synthetic XML; a
deliberately invalid document state (if one can even be constructed — task
2.3's immutability should make this hard) fails validation loudly rather
than producing a file AT would reject.

### 3.4 Object storage (`stored_files`)

- `stored_files` per §6.12's schema (`id, kind, subject_type, subject_id,
  storage_key, sha256, size, created_at`) — insert-only, `kind` restricted
  to `sealed_pdf|saft|attachment` (§6.12's own enum).
- MinIO adapter (already provisioned in `docker-compose.yml`, unused until
  now) behind a small port (`FileStore` or similar) — put/get/exists by
  `storage_key`, SHA-256 computed on write and checked on read.
- Lives in **Output** (decision 1) — every current consumer (`sealed_pdf`
  from task 3.6, `saft` from task 3.3) is Output's own concern; `attachment`
  (Purchases' scanned supplier documents) is a later phase's caller, this
  task just needs the schema/port to already allow it.

**Accept:** a file written through the port round-trips byte-for-byte on
read; a SHA-256 mismatch on read is a hard failure, not silently ignored;
company isolation test (a `stored_files` row belongs to exactly one company,
RLS-enforced like every other company-scoped table).

### 3.5 `DocumentPdfRenderer` + templates

- Spike (decision 7): render one invoice through both mPDF and Twig →
  Gotenberg, 🧑 owner picks, before building the real thing.
- `DocumentPdfRenderer` port (module Output), rendering from **stored data
  only** — `document_lines`, `document_tax_summary`,
  `customer_snapshot`/`issuer_snapshot` — never a live join to
  `customers`/`company_profile`, so a later address/logo/name change never
  alters how an old document prints (§7.8's explicit requirement).
- `template_version` recorded per document at render time; old template
  versions stay in the codebase (§7.8) — this task's own template is
  version 1, nothing to migrate yet.
- Contents: all legal mentions (hash 4-chars + certification mention,
  ATCUD, QR, "não serve de fatura" for working documents — reusing task
  2.5/2.8's existing data, not recomputing any of it).
- `document_prints` (§6.12: `id, document_id, kind [print|download|email],
  copy_label, user_id, occurred_at`), insert-only — logged on every render
  request, driving original/copy/reprint mentions. Exact copy/reprint
  wording rule is still `[VERIFY]` (prerequisite status) — resolve from
  `despacho-8632-2014.pdf` (already in `docs/legal/`) as part of this task;
  stop and ask if it isn't actually in there.
- No storage dependency for the common case: unsealed PDFs are generated on
  demand and not persisted (§7.8's explicit "PDFs are generated on demand,
  not stored"); only task 3.6's sealed output goes to `stored_files`.
  Optional short-lived render cache is a stretch goal, not an accept
  criterion.

**Accept:** the same document renders byte-identically on repeat calls
(deterministic, no timestamp/random content leaking in); a document's PDF
still renders correctly after its customer's name/address changes
(snapshot data proven, not live data); every render is logged in
`document_prints` with the right `kind`; a working document's PDF carries
"Este documento não serve de fatura" and an invoice's doesn't.

### 3.6 `ElectronicSealer`

- Port (module Output) with a **fake adapter** (deterministic fake
  signature, dev/test only) shipped regardless of provider status — this is
  what task 3.7 (email) and everything downstream builds against.
- **Real adapter**: blocked on the still-open trust-provider decision
  (prerequisite status) — implement once a provider and its API docs exist;
  until then this task ships fake-only and is explicitly not "done" for
  production readiness, only for keeping the rest of the phase moving.
- Remote-sealing flow per §7.8: render PDF → hash it → provider signs the
  hash → embed the signature (PAdES) → the PDF itself never leaves the
  platform unsealed-and-unsent. Sealed output stored via task 3.4
  (`kind: sealed_pdf`, SHA-256, WORM-appropriate retention metadata —
  actual object-lock configuration is an infrastructure/hosting concern,
  noted for §14.3 item 23, not this task).
- Only documents **sent electronically** get sealed (§7.8) — a
  print-and-hand-over document never enters this path.

**Accept:** with the fake adapter, a sealed document is stored exactly once
and re-downloaded identically on every subsequent request (no re-sealing,
no drift); the seal is applied only on the "send electronically" path, not
on every render; when a real provider exists, the port makes swapping
adapters a config change, not a rewrite (proven by the fake/real split
existing from day one, not retrofitted).

### 3.7 Email sending of documents

- Async (Messenger, Redis transport — already provisioned, `PLAN.md` task
  0.6's own note that it's "used from Phase 3"). Depends on 3.5 (render)
  and, for anything sent electronically, 3.6 (seal) + 3.4 (storage of the
  sealed artifact) being in place first.
- Uses whatever mailer transport is already configured for the rest of the
  app (Platform's own auth emails) — no new transport decision needed here.

**Accept:** emailing a document logs a `document_prints` row (`kind: email`)
and, if sent electronically, results in exactly one stored sealed PDF
referenced by the email, not re-rendered/re-sealed per retry; a transient
mail-server failure retries without re-sealing.

### 3.8 Web: AT status and series screens

- Series screen (task 2.2's UI) drops the manual validation-code field;
  "Ativar" now calls the real backend, which calls AT (task 3.1)
  synchronously and shows the result (including AT's own rejection message
  on failure) directly, no polling needed given the synchronous design
  (decision 3).
- Document detail view (task 2.11) gains an AT-communication status
  indicator (`pending`/`sending`/`accepted`/`rejected`/`failed`, task 3.2's
  read-model addition) and a manual "retry" action for a `failed` row
  (§7.5's outbox exposed in the UI, not just the sweeper).
- Download/email actions on the document detail view, wired to tasks 3.5/3.7.

**Accept:** Playwright e2e — activate a series and see a real AT validation
code appear (against the test environment, decision 10); issue a document,
watch its AT status move from `pending` to `accepted`; download its PDF and
confirm the legal mentions are present; a deliberately `rejected`
communication is retryable from the UI and moves to `accepted` once
whatever caused the rejection is fixed.

---

## Phase 3 exit criteria

- Series register/finish/cancel and invoice/work/payment communication all
  proven against AT's **real test environment** — not simulated — for at
  least one document of each type (FT, FS, FR, NC, ND, OR, PF, NE, RG).
- SAF-T (PT) export validates against the official XSD for a company with a
  representative document mix.
- A sealed PDF is produced in sandbox (fake adapter acceptable if the trust
  provider still isn't chosen by then) and re-downloads identically rather
  than re-sealing.
- `make openapi` run; spec and web client regenerated and committed.
- `make lint` and `make test` green on every task, not just at the end.
- 🧑 Owner review of the WS-Security cipher implementation (task 3.1) before
  it's considered done — same standing rule Phase 2 applied to
  `DocumentSigner`/`PriceCalculator`, extended here since this code also
  handles AT credentials in transit.
- Remaining `[VERIFY]` items from the prerequisite-status section either
  resolved and cited, or explicitly still open and flagged to the owner —
  never silently assumed.
- 🧑 Trust service provider still not chosen is **not** a phase-3 blocker by
  design (decision/task 3.6's fake-adapter carve-out), but is called out
  again here so it doesn't quietly become permanent.
