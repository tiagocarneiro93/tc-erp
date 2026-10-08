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
11. **Error mapping, added after task 3.1's live-test fixes**: every
    AT-integration client (task 3.1's `SeriesWSClient`, task 3.2's invoice/
    receipt client, any later one) must follow the same convention as the
    rest of the app (technical-scope.md §9.1, `Shared\Domain\Exception\ProblemDetails` +
    `ProblemDetailsExceptionListener`, task 0.11) rather than letting a raw
    `\RuntimeException` fall through to a generic 500. Two categories, not
    one blanket rule:
    - **User/business-actionable states** (the caller can do something
      about it, e.g. "this company hasn't configured AT credentials yet")
      get a proper exception implementing `ProblemDetails` with a real
      status and stable `type`. `Shared\Domain\Company\AtCredentialsNotConfigured`
      (404, moved there from `Company\Domain\Exception` specifically so
      every module's AT client can throw the same one, not just Company's
      own handlers) is the first of these and should be reused, not
      duplicated, by task 3.2's client.
    - **"Should never happen" / deployment-config / AT-contract-violation
      states** (missing or corrupt key/cert files, AT returning a
      response shape our code doesn't recognize) stay as plain exceptions
      falling through to the generic 500 — this already matches
      `OpenSslDocumentSigner`'s existing precedent for the exact same
      category of problem, and converting every single one into a
      "friendly" client-facing error would be less consistent with the
      codebase, not more.

12. **Receipts (`RG`) are not communicated — reverses decision 8** (found
    while building task 3.2). `Fatcorews.wsdl`'s `PaymentTypeType` enumerates
    **only `RC`** ("Recibo emitido no âmbito do regime de IVA de Caixa"), and
    `at-ws-efatura-aspetos-especificos.pdf` §2.1.7.1 item 1.6.4 says the same.
    `RegisterPayment` can therefore never carry this system's `RG` receipts —
    AT would reject every one, and no other operation takes them.
    `IssueReceiptHandler` correctly stays without an `enqueue()`; decision 8's
    "gap" was never a gap. Consequences: the phase exit criterion's "one
    document of each type … RG" cannot be met for `RG` and is amended below;
    cash-VAT `RC` receipts (a different document type, not issued by this
    system yet) would be the only receipts ever communicated.
13. **Task 3.2's e-Fatura client uses hand-built SOAP envelopes, not
    `ext-soap` — deviation from decision 4**, for e-Fatura only (series stays on
    `SeriesWSClient`, which is live-verified). Reason: the request body is built
    by `EFaturaRequestBuilder` and every output is validated in the unit suite
    against the XSD embedded in `Fatcorews.wsdl` itself (`tests/Support/
    FatcorewsSchema`), and the client's envelope/response handling runs against
    a fake HTTP transport — neither is possible through `SoapClient`'s
    WSDL-driven array serialisation without `ext-soap` and a live AT. The wire
    format is the WSDL's own: SOAP 1.1, document/literal, empty `SOAPAction`.
14. **Field decisions for the e-Fatura bodies**, each cited in
    `EFaturaRequestBuilder`'s docblock: `HashCharacters` is `0` while the
    certificate number is `0` (manual item 1.6.9: "ou o valor «0» … caso o
    documento seja gerado por um programa não certificado"); `ATCUD` is the
    document's real ATCUD (the manual's "preenchido com 0 até à sua
    regulamentação" predates Portaria 195/2020; the WSDL accepts any 1–100
    character string); `DebitCreditIndicator` is `D` for `NC`, `C` otherwise
    (AT's own `saft-pt-sample-instance.xml`); `Amount`, not `TotalTaxBase`;
    `LineSummary` amounts are reconciled to `document_tax_summary` (per-line
    amounts are informational under `per_group` rounding, §7.9.5) so they
    always add up to `NetTotal`.
15. **Outcome classification** (`AtCommunicationOutcome`, from the manual's
    response-code tables): `0` → accepted; the operation's own "already
    registered" code (`-10` invoice, `-22` work) → accepted (a re-send after a
    crash between AT's answer and our write); positive codes (1–99:
    authentication/envelope) and `-97`/`-99` → `failed`, retried with
    exponential backoff (2, 4 … 256, then 360 minutes, 10 automatic attempts,
    then a person retries); every other negative code → `rejected`, never
    retried automatically. A manual retry (`POST …/documents/{id}/at-
    communication/retry`, 202) resets the attempt budget.
16. **Cancellation and `ChangeInvoiceStatus`**: no `ChangeInvoiceStatus`/
    `DeleteInvoice` call is built. Task 2.10 only lets a document be cancelled
    before AT confirmed it (`pending`/`failed`/`rejected`), and the consumer
    reads the document's *current* status when it sends, so a cancelled
    document is simply registered with `InvoiceStatus = A`. What *does* change
    status after acceptance is a working document reaching `F` once fully
    converted (task 2.8) — that is `ChangeWorkStatus`, enqueued as a
    `document_status` outbox row only when the registration was already
    `accepted`. Training-series documents (`tipoSerie F`) are never enqueued —
    the manuals are silent; conservative default, owner may override.

17. **SAF-T validation approach** (task 3.3). AT's `SAFTPT1.04_01.xsd` is an
    **XSD 1.1** schema (`vc:minVersion="1.1"`, `xs:assert` business rules); PHP
    validates through libxml2, which only speaks XSD 1.0 and refuses to compile
    it. The official file is kept byte-identical (`api/resources/saft/`, a test
    enforces it); `Xsd10Projection` derives a 1.0 projection at runtime (drops
    the `xs:assert`s, turns the one `xs:all` with unbounded children — the
    unused ledger lines — into a `choice`), and `SaftAssertionChecker` re-
    implements the assertions that apply to the billing sections (zero-tax ⇔
    exemption reason, reason and code together, `TaxBase` exclusivity, `RC`
    payments need `Tax`), each with a passing and a failing test. Proven on
    AT's own `saft-pt-sample-instance.xml` (0 errors) and on mutated copies of
    it (duplicate `CustomerID`, stripped exemption reason → reported).
18. **SAF-T field mapping** follows AT's own sample where the XSD is silent:
    line `CreditAmount`/`DebitAmount` = the line's taxable value after all
    discounts (`net_amount`; `DebitAmount` for `NC`), `UnitPrice` = the price
    after discounts, line `SettlementAmount` = total discount on the line;
    section `TotalDebit`/`TotalCredit` leave out cancelled (`A`) documents but
    `NumberOfEntries` counts them (verified by recomputing the sample's own
    headers). Master data lists only what the period references (scope §7.7,
    still `[VERIFY]` — no prose spec in `docs/legal/`), customers taken from
    the documents' frozen `customer_snapshot`, never from Parties.

19. **PDF engine — spike done, provisional pick: mPDF, owner to confirm**
    (replaces decision 7's "spike, then ask"; `docs/plans/assets/phase-3-pdf-spike/`
    has the same 40-line invoice rendered by both engines). Same Twig HTML, run
    through both: **layout equivalent** (tables, totals block, QR, accents,
    pt-PT amounts); mPDF ~0.2 s in-process, Gotenberg ~0.25–0.5 s over HTTP;
    **both made byte-deterministic** by `PdfDeterminism` (they stamp the current
    time and a random file id — rewritten in place at the same length, so the
    cross-reference table stays valid). Differences that matter: mPDF is a PHP
    library (needs `ext-gd`, now in the Dockerfile and CI) with a CSS 2.1-level
    renderer — no flexbox/grid, so templates stay table-based, as v1 is;
    Gotenberg is full Chromium CSS but is one more container (large image) to run,
    monitor and patch, and it needed one extra CSS rule (`tr { page-break-inside:
    avoid }`, harmless for mPDF) to stop a table row splitting across pages.
    mPDF is wired (`PdfEngine` alias); `GotenbergPdfEngine` stays in the tree,
    swapped in by changing that one alias. Delete whichever loses once decided.
20. **Original / copy marking** (resolves the `[VERIFY]` on "exact copy/reprint
    mention rules"): Despacho 8632/2014 §2.2.15 requires a second copy to keep
    the original content and carry *some* expression showing it is not the
    original — it prescribes **no wording**. House convention: the first thing
    handed out (print, download or e-mail — every one is logged in
    `document_prints`) is "Original", then "Duplicado", "Triplicado", "n.ª via".
    ("Cópia do documento original", §2.4/§2.5, is the different case of
    documents re-created from a backup.)
21. **Issuer identity is frozen at issuance** (`documents.issuer_snapshot.identity`,
    written by `IssueDraftHandler` from the new `CompanyFiscalIdentityProvider`):
    name, NIF, address, contacts and the cash-VAT regime as of that moment. The
    PDF header, the SAF-T `CashVATSchemeIndicator` and the AT request all read it
    first; documents issued before it fall back to the company's current data.
    This closes 3.2's "cash-VAT flag is not frozen" item without touching the
    existing snapshot provider.

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

**Status: done, verified against AT's real test environment (2026-09-22,
owner-run) — see `docs/PLAN.md` task 3.1 for the full breakdown.**
Everything above is built and covered by the everyday test suite (against
`FakeSeriesWebserviceClient`, a plan refinement not in this write-up — see
`docs/PLAN.md`). `registarSerie` returns `codResultOper: 2001` and a real
`AA`-prefixed `codValidacaoSerie`, closing the accept criterion and
confirming the RSA-padding assumption in `OpenSslAtRequestCipher` was
correct. The real bug the live run caught was in
`SeriesWSClient::buildSecurityHeader()` — a missing `<wss:Security>`
wrapper element, not the cipher — see `docs/PLAN.md` for the full
diagnosis.

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

**Accept:** issuing any of FT/FS/FR/NC/ND/OR/PF/NE results in exactly one
`at_communications` row that the consumer picks up and sends to AT's test
environment, verified as actually accepted there (decision 10); a
deliberately malformed request (e.g. missing required field) comes back
`rejected` with AT's own message stored, not retried forever; killing the
worker mid-processing and restarting it doesn't lose or duplicate the row
(the sweeper's safety-net role, tested directly); a receipt is never
enqueued (decision 12), with a regression test so nobody "fixes" that later.

**Status: built and covered by the everyday suite (against
`FakeAtDocumentWebserviceClient`); live verification against AT's test
environment is the owner-run step still to do** (`SeriesWSClientLiveTest`'s
counterpart is not written — it needs the owner's AT sub-user, see
`docs/PLAN.md` task 3.2). See decisions 12–16 above for what changed against
the original write-up. Also fixed here: a regression where
`DoctrineAtCommunicationQueue::enqueue()` had been moved onto the independent
`audit_log` connection, which let an invoice's outbox row commit even when
issuance rolled back (orphan `pending` rows); only `recordResolved()` needs the
independent connection.

**Resolved by decision 21 (task 3.5) — kept for the record:** `CashVATSchemeIndicator` was always `0` —
the issuer snapshot frozen at issuance (`documents.issuer_snapshot`) does not
carry the company's cash-VAT flag, so a company under IVA de Caixa is
communicated as if it were not. The fix belongs in the Company module's
`IssuerSnapshotProvider` (add `cash_vat`; `DoctrineAtCommunicableDocumentReader`
already reads it). Not done: the file could not be read in this session.
Must be fixed before any cash-VAT company is onboarded, and before 3.3 (SAF-T
has the same field).

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

**Status: built and covered by the everyday suite** — `GET /companies/{c}/saft?from=&to=`
(permission `reports.read`), streaming `XMLWriter` generator, validated against
the official schema before the file is offered (a file that does not validate
is a 500 and is deleted, never a download). Full-period billing export
(`TaxAccountingBasis` = `F`) only; **the monthly-communication export is not
built**: nothing in `docs/legal/` says how it differs (the prose SAF-T spec is
still unobtained) — 🧑 owner to supply it or confirm the full export is what the
monthly submission uses. Found and fixed on the way: `document_lines.
exemption_reason_text` was never populated at issuance (always `NULL`); issuance
now freezes AT's "Menção que consta da fatura" wording into it (the column is
in scope §6.6) — the schema requires it and printed documents will too.
**Open for the owner / follow-ups:** (1) `ProductCompanyTaxID` is a placeholder
(`999999990`) until TCWeb's real NIF is set in `SAFT_PRODUCT_COMPANY_TAX_ID`;
(2) `HashControl` is the *current* configured signing-key version because
documents do not record the version that signed them (scope §6.6 says
`hash_control` is the key version; the implementation stores the printed
mention there) — fine until the first key rotation, needs a new column before
it; (3) the cash-VAT flag is taken from the company's *current* regime until the
issuer snapshot carries it (see 3.2); (4) not covered: `MovementOfGoods`
(Phase 4), supplier master data, withholding tax, foreign currency;
(5) the file is returned as a download — persisting it as a `stored_files` row
is task 3.4's wiring.

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

**Status: built and covered by the suite against real PostgreSQL and a real
MinIO** — `Output` module (own four Deptrac layers), `stored_files` migration
(`Version20261007100000`: insert-only, RLS, `kind`/`sha256`/`size` CHECK
constraints in the database itself), `FileArchive` cross-module port in front
of `StoredFiles` (object uploaded **before** its row is written, SHA-256 from
the bytes handed in, recomputed on every read — a mismatch is a hard
`StoredFileIntegrityViolation`, and a failed `copyTo` deletes the partial
file), `S3ObjectStorage` on `async-aws/s3` (new dependency, 4 packages).
Keys are `<company>/<kind>/<year>/<id>`; reaching a file at all requires seeing
its RLS-protected row. The SAF-T export (3.3) is now archived: every export is
kept, `X-Stored-File-Id` returned. **Not here, by design:** bucket policy —
versioning, object lock/WORM for the 10-year retention (DL 28/2019 Art. 27.º
§2), lifecycle rules, separate backup location — is infrastructure, to be set
where the production bucket is created (scope §14.3 item 23). `attachment` has
no caller yet.
**Environment finding:** `docker-compose.yml`'s `minio/minio:latest` is no
longer pullable from Docker Hub ("pull access denied"); switched to MinIO's
official `quay.io/minio/minio:latest` (not verifiable from this sandbox, which
blocks quay.io — the integration tests ran against `bitnamilegacy/minio`, also
a real MinIO). CI (`backend.yml`) now starts MinIO for the tests; also not
verifiable from here.

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

**Status: built and covered** — `GET /companies/{c}/documents/{id}/pdf?kind=download|print`
(`documents.read`), `PrintableDocumentReader` port (stored data only; only
*issued* documents exist behind it, so a draft is a 404), `TwigDocumentPdfRenderer`
(template chosen by the document's own recorded `template_version`; `strict_variables`;
v1 = `api/templates/pdf/v1/document.html.twig`), `PdfEngine` port with mPDF and
Gotenberg adapters, QR built to `at-qrcode-spec.pdf` §2 (ECC M, Byte, version ≥ 9;
30 mm image with 2.5 mm margin), `document_prints` (`Version20261007110000`, insert-only,
RLS, `kind` CHECK) with an advisory lock per document so two concurrent requests
cannot both be the "Original". Legal content, by source, is listed in the
template's header comment: hash mention (§2.2.2), date format (§2.2.4), tax base /
breakdown / total on the last page only (§2.2.11), exemption wording tied to the line
(§2.2.14), original/copy (§2.2.15), "Este documento não serve de fatura" (§1.2), training
header and mention (§1.5), QR, ATCUD. Proven: byte-identical repeat renders (seconds
apart), a PDF unchanged after the customer's address and the company's name/address/
VAT regime change, every request logged with kind and label, 404 for drafts,
unknown ids and other companies' documents.
**Not covered:** PDFs of receipts (`RG`) — their QR requirement is the open question
of task 2.9, not guessed here; company logos (need object storage wiring, a later
task); the software producer's address for training documents is blank until set in
`SOFTWARE_PRODUCER_*` (the NIF is `SAFT_PRODUCT_COMPANY_TAX_ID`, still a placeholder).

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

**Status: fake-only, built and covered** — `ElectronicSealer` port (Output domain:
`seal(CompanyId, pdf): string`, `SealingFailed`), `FakeElectronicSealer` (appends a
visibly-fake marker, the SHA-256 and the company id; **not a signature**; bound in
`APP_ENV=dev` and `test`) and `UnconfiguredElectronicSealer` (the default everywhere
else: sealing fails loudly rather than letting an unsealed PDF go out as if sealed).
`SealedDocumentPdfs::obtain()` is the only way to a sealed PDF: lock the document
(advisory lock shared with the print log) → return the archived `sealed_pdf` if there is
one (verified against its SHA-256, never re-rendered or re-sealed) → otherwise render with
the label the print log yields, seal, archive. A failed seal stores nothing. Sealing *is*
the document's electronic hand-out, so in the same transaction it logs one
`document_prints` row (kind `email`) — which is also how the label of the stored file is
found again (the earliest `email` row). Plain downloads/prints never come through here
(the "send electronically" path only — task 3.7).
Proven: unit tests (sealer called once across repeated calls, same bytes, failure stores
nothing, drafts refused) and a functional test on a real issued document with PostgreSQL
and MinIO (one `stored_files` row per document, identical bytes).
**Open:** the real PAdES adapter is blocked on the trust-provider decision (§14.3 item 15),
and so is whether the seal uses one platform certificate or a per-company one (the port
already receives the `CompanyId`). Swapping it in is the one alias in `config/services.yaml`.

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

**Status: built and covered** — `POST /companies/{c}/documents/{id}/send` (the route
`technical-scope.md` §9 names; `documents.issue`, `Idempotency-Key` required, body
`recipients[]` (default: the customer's address) + optional `message`; 202 = queued).
Two halves: `EmailDocumentHandler` (`command.bus`: permission, the document is issued —
a draft is a 404 —, somebody to send to, audit `document.email_requested`, dispatch after
commit) and `SendDocumentEmailHandler` on a new `output.bus` (no surrounding transaction,
like `at.bus`): step 1 obtains the sealed PDF in a transaction of its own, so the seal,
its stored file and its print record are committed *before* anything is mailed; step 2
mails it (`SymfonyDocumentMailer`: platform From address — a company's domain would fail
SPF/DKIM —, the company in Reply-To, PDF attached; subject/body in pt-PT) and audits
`document.email_sent` with the stored file's SHA-256. A mail-system refusal
(`DocumentMailFailed`) propagates and Messenger retries the whole message — step 1 then
just finds the stored file: **never re-sealed on retry**. An undeliverable address or a
vanished document is `Unrecoverable` (no pointless retries).
Decisions: (a) `document_prints` gets one `email` row per *sealed file* (written when it
is sealed), not per send: re-sending the identical file is not a new rendering, and each
send is in the audit log with recipients and hash; if every send of a sealed file fails
for good, the document still counts as handed out — the safe direction, since it can only
make a later PDF a "copy", never a second "Original". (b) Delivery is at-least-once: a
crash between a successful send and its audit record mails the same sealed file again.
(c) Deptrac: `Output.UIHttp` may use `Shared.Infrastructure` for `IdempotencyKeyGuard`
only — the same narrow exception `Fiscal.UIHttp` and `AtIntegration.UIHttp` already have.
Proven: unit (handlers, mailer adapter, retry-without-resealing, unrecoverable cases) and
functional through HTTP + queue + real PostgreSQL/MinIO (attachment is the archived file,
second send reuses it, idempotent replay queues nothing, drafts/invalid/missing key refused).
**Not covered:** the web UI for this (task 3.8); a failure transport/dead-letter view
(a message that exhausts its retries is only logged by Messenger today); per-company
sender names.

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

**Status: built; e2e run and green locally (all three specs).**
- Series screen: already done in 3.1 (no validation-code field; "Ativar" calls the backend,
  which calls AT, and shows 404 → "configure the AT credentials first" / 422 → "AT
  rejected the registration"). Nothing more to build there.
- Document detail (`DocumentDetail.tsx`): `DocumentAtStatus` (pt-PT badge per
  `pending|sending|accepted|rejected|failed`, AT's own response code/message and the
  attempt count, "Tentar novamente" only when the backend says `can_retry`, with an
  `Idempotency-Key`; polls every 5 s while the communication is in flight; a document
  with no communication — training series — says so); "Descarregar PDF" and "Imprimir"
  (plain links to `/pdf`, so no hand-written `fetch`; both are logged as hand-outs by the
  backend); "Enviar por email" (`EmailDocumentDialog`: recipients, optional message; blank
  recipients = the customer's address, resolved by the backend; "Envio agendado." on 202).
- Tests: `DocumentAtStatus.test.tsx` (10 Vitest: every status label, AT answer shown, no retry
  unless `can_retry`, retry sends an `Idempotency-Key`, refused retry reported) and the
  Playwright flow extended (AT credentials step, "Comunicado à AT" on the first invoice, a
  real PDF fetched through the link, the e-mail dialog).
- **Deviations from the accept criterion above, on purpose:** (a) the e2e runs against fake AT
  clients, not AT's test environment — a browser test must not depend on a government
  server, and the live check stays the owner-run test of task 3.1g/3.2; (b) a `rejected →
  retry → accepted` run is covered by the Vitest component test and by the backend flow
  test (`AtCommunicationFlowTest`), not in the browser, because the fake cannot be scripted
  from a browser session; (c) that the PDF carries the legal mentions is proven by task 3.5's
  tests; the e2e only checks that the link serves a PDF.
- **The e2e job was not actually working before this task** and is fixed here: activating a
  series needs the company's AT credentials (the spec never entered them), and CI started the
  API as `dev`, i.e. against the real AT. New `APP_ENV=e2e` (`config/services_e2e.yaml`,
  `when@e2e` blocks in `messenger.yaml`/`monolog.yaml`): fake AT clients and sealer, a
  **synchronous** queue (documents are "communicated" and e-mails sent inside the request —
  there is no worker in that job), real sessions. CI also now starts MinIO and has `gd`,
  because sealing/PDF run in that flow, and starts `php -S` with `variables_order=EGPCS`
  (without it the built-in server did not see the `APP_ENV` etc. it was started with and
  silently ran as `dev`). The spec's timeout is raised to 120 s (the flow takes ~50 s).
  The CI job itself has not run on GitHub — only the equivalent commands locally.

### Live-AT test suite (added after 3.8)

`api/tests/LiveAt/` + `make test-at-live` (own config `phpunit.live-at.dist.xml`, never part
of `make test`/CI): the production series and e-Fatura adapters wired straight to
`api/.env.test.local` (sub-user, password, **`AT_TEST_NIF`**, certificate, public key,
endpoints), no database or UI. Refuses to run (a failure, not a skip) unless both endpoints
are AT's **test** ones (722/723/725 on `servicos.portaldasfinancas.gov.pt`; production is
422/423). `SeriesLifecycleTest` is **fixed** (training FT series, AT's exact codes
2001/2003/2004; only the series code is random per run). `DocumentCommunicationTest` sends
the scenarios in `scenarios.dist.php` (FT ×3 incl. mixed VAT/discount/exempt and cancelled,
FS, FR, NC, ND, OR, PF, NE, NE→F via `ChangeWorkStatus`, and a deliberately wrong FT that must
be rejected), overridable per key in a git-ignored `scenarios.local.php` or narrowed with
`AT_TEST_ONLY`; amounts come from the real `PriceCalculator`. Requests/responses and a
`report.md` land in `api/var/at-live/<run>/` (password material blanked). Checked offline in
the normal suite (`tests/Unit/LiveAt`, 47 tests): the endpoint guard, configuration,
scenario merging, harness wiring with a throwaway certificate, and that every shipped
scenario yields a request that validates against AT's own `Fatcorews.wsdl` schema.
**Not yet run against AT** — it needs your certificate and sub-user.
**Finding to confirm with the first run:** AT's `finalizarSerie` code 4047 says the last
document number must be greater than the start of the sequence; `Series::finish()` accepts a
series that issued only its first document. The series test probes exactly that boundary and
records AT's answer.

---

## Phase 3 exit criteria

- Series register/finish/cancel and invoice/work communication all proven
  against AT's **real test environment** — not simulated — for at least one
  document of each type (FT, FS, FR, NC, ND, OR, PF, NE). `RG` is excluded:
  AT's webservice has no operation for it (decision 12).
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
