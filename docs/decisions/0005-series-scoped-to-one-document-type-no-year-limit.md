# 0005 — A series is scoped to one document type, with no forced year rotation

**Status:** Accepted
**Date:** 2026-09-12

## Context

technical-scope.md §14.2 decision #10 originally recommended "one series per
document type per year (e.g. `FT 2027A`), more series allowed" — a
convenience default, not something read from a legal source at the time.

While reviewing the legal sources needed to unblock Phase 2, the owner
uploaded Portaria n.º 195/2020 (ATCUD/QR requirements) and asked whether
companies could instead freely create and manage their own series, since
nothing in what had been read so far seemed to force a yearly reset or a
single document type per series.

Reading the actual texts settled this precisely, in both directions:

- **Series ARE scoped to exactly one document type.** Portaria 195/2020
  Art. 2 requires the document type as one of the elements communicated to
  the AT when requesting a series' validation code — there is no way to
  obtain one `código de validação` covering more than one document type.
  Despacho 8632/2014 §1.8 confirms this from the other side: a series
  identifier "não pode ser repetido no mesmo contribuinte, para o mesmo
  tipo de documento" (cannot be reused, for the same taxpayer, for the same
  document type) — implying distinctness is tracked per document type, not
  across all of a company's series at once.
- **Series are NOT scoped to one calendar year.** Despacho 8632/2014 §1.6
  requires a series to run for a minimum of one fiscal year but explicitly
  permits longer ("séries plurianuais"), and §2.1.5 specifies how the hash
  chain behaves across a fiscal-year boundary for such a series (the first
  document of the new year chains from the previous year's last hash in
  the same series/type — the chain does not reset). A yearly-rotating
  series was our own convenience default, not a requirement.

Three options were considered: (a) keep the original recommendation
(one series per document type per year); (b) drop the document-type
constraint too, since the owner's question read that way at first; (c) the
legally accurate middle ground — one document type per series, no forced
year boundary.

## Decision

Option (c). A `Series` is created for exactly one document type and keeps
running until the company decides to stop using it — no automatic
per-year rotation, no schema constraint tying it to a calendar year.
Companies may still choose to start a new series each year if they want
the numbering to reset (a UI/workflow convenience, not a rule the system
enforces), and may run a single series across many years if they prefer.

The hash chain (Phase 2 task 5, `DocumentSigner`) must be built with this
in mind from the start: chaining is always "the previous document in this
series/type", full stop — there is no special case for "first document of
a new year" beyond that being, mechanically, just the next document after
whatever the series' last one was.

## Consequences

- technical-scope.md §14.2 decision #10 is updated to this outcome, citing
  Despacho 8632/2014 §1.6/§1.8 and Portaria 195/2020 Art. 2.
- `docs/plans/phase-2.md`'s Series task (task 2) designs the entity with a
  `document_type` field fixed at creation and no year/period field at all
  — not a nullable one, not one defaulted to "current year": the concept
  doesn't exist in the data model.
- The hash-chaining logic in task 5 needs no year-boundary special case;
  "previous document in this series" is a single, uniform lookup.
- This does not change anything about Phase 3's actual AT series
  communication (still deferred, per the existing plan) — only the shape
  of the `Series` entity Phase 2 builds against a manually-entered
  validation code.
