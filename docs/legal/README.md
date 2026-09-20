# Legal and technical sources

Claude Code must resolve every **[VERIFY]** item from these documents (citing document and section). Keep the files here, named clearly, and note the version/date downloaded. Always download the **current consolidated version** from official sources (Diário da República, Portal das Finanças, e-Fatura documentation).

**Known limitation:** this sandbox's network egress is blocked for `portaldasfinancas.gov.pt` (and possibly other government domains) — Claude Code cannot fetch these directly and needs the file uploaded instead (save the page as PDF, or paste its content).

## Certification, invoicing and SAF-T

| File (suggested name) | Content | Needed for | Status |
|---|---|---|---|
| `portaria-363-2010.pdf` | Certification of invoicing software; Art. 6.º defines the Hash signing string (InvoiceDate;SystemEntryDate;InvoiceNo;GrossTotal;PreviousHash, RSA, base64, 4 printed chars at positions 1/11/21/31) | Phase 2, 6 | ✅ DR 1.ª série n.º 120, 23 Jun 2010 |
| `despacho-8632-2014.pdf` | Technical requirements for invoicing software (signing, access control, SAF-T rules, series rules, customer/product immutability once issued, credit-note/cancellation constraints) | Phase 0 (§8.1), 2 | ✅ DR 2.ª série n.º 126, 3 Jul 2014 |
| `dl-28-2019.pdf` | Invoicing, archiving and electronic invoice rules. Art. 7.º §3 is the statutory basis for the QR/ATCUD mandate; Art. 7.º §4 states the "series run at least one fiscal year" rule in primary law (not just Despacho 8632/2014's gloss on it); Art. 4.º sets the AT-certification threshold (>€50k prior-year turnover, superseding Portaria 363/2010's old phased thresholds — Art. 44.º revokes that Portaria's Arts. 1/2/8/9, leaves Arts. 6/7 intact); Chapter V (Arts. 19–30) is the 10-year archiving regime, incl. Art. 27.º §2's requirement that originals and backups sit in physically/logically distinct locations. Does not itself state the legal conditions for cancelling an already-issued document (only requires logging that one happened, Art. 7.º §5) — that's `civa-extracts.md`'s Art. 29.º §7 | Phase 2, 3 | ✅ DR 1.ª série n.º 33, 15 Feb 2019 |
| `oficio-circulado-30213-2019.pdf` | AT administrative instructions on DL 28/2019 (incl. electronic invoices, points 14–16) | Phase 2, 3 | |
| `dl-198-2012.pdf` | Communication of invoice elements to the AT (art. 3) and goods in circulation | Phase 3, 4 | |
| `portaria-195-2020-atcud-qr.pdf` | ATCUD composition (`CódigoDeValidação-NúmeroSequencial`) and the series-communication requirements to obtain it (series id, document type, start number, start date) | Phase 2 | ✅ DR n.º 157/2020, Série I, 13 Aug 2020 |
| `at-qrcode-spec.pdf` | AT's QR code technical specification v1.0: full field table (A–S), concatenation/formatting rules, four worked examples (Fatura, Fatura simplificada, Fatura pró-forma, Guia de transporte) | Phase 2 | ✅ v1.0, Aug 2020 |
| `SAFTPT1.04_01.xsd` | SAF-T (PT) XML schema — confirms `TaxCode` (RED/INT/NOR/ISE/OUT), `TaxCountryRegion` (PT/PT-AC/PT-MA + ISO countries), `InvoiceType` enum (no RG — receipts are a separate `Payments/Payment` structure with their own `PaymentType` RC/RG), `ATCUD` element on invoices/movements/work documents/payments | Phase 2, 3 | ✅ v1.04_01 |
| `saft-pt-sample-instance.xml` | AT's own demo SAF-T instance (not itself a legal source — a worked example) confirming field shapes: `Hash` (172-char base64 RSA signature), `ATCUD`, `InvoiceNo` format, `DocumentStatus` | Phase 2, 3 | ℹ️ reference only, pre-dates the ATCUD mandate (2017 data, `ATCUD` shown as `0`) |
| `at-tabela-codigos-motivo-isencao.pdf` | Official table of VAT exemption/non-liquidation reason codes (M01–M99), invoice wording and legal basis per code | Phase 1, 2 | ✅ V4.0, 18 Jun 2026 |
| `civa-extracts.md` | CIVA Art. 18.º VAT rates (mainland, Açores, Madeira — all nine confirmed against the AT portal) and Art. 29.º §7 (any change to an invoice's value/tax, for any reason, requires a rectifying document — not a cancellation; resolves task 2.10) | Phase 1, 2 | ✅ Art. 18.º + Art. 29.º both pasted by the owner and confirmed |

**Not yet obtained, still needed:** `saft-pt-structure.pdf` / `saft-pt-technical-notes.pdf` (the prose spec alongside the XSD above — useful for field-level notes the schema alone doesn't carry, but the XSD covers the structural `[VERIFY]` items Phase 2 needs); `oficio-circulado-30213-2019.pdf`.

## AT webservices

| File | Content | Needed for | Status |
|---|---|---|---|
| `at-ws-efatura-aspetos-genericos.pdf` | e-Fatura webservice – generic aspects: implementation phases, SOAP header (WS-Security), client certificate (CSR), endpoints | Phase 3 | ✅ v2.0, Oct 2025 |
| `at-ws-efatura-aspetos-especificos.pdf` | e-Fatura webservice – specific aspects: SOAP body structures, examples, error codes | Phase 3 | |
| `Fatcorews.wsdl` | WSDL for document communication | Phase 3 | |
| `at-ws-series.pdf` | Series communication webservice manual | Phase 3 | |
| `at-ws-transport.pdf` | Transport documents webservice manual | Phase 4 | |

## Electronic invoices: qualified signature / seal (from 1 January 2027)

| File | Content | Needed for | Status |
|---|---|---|---|
| `dl-28-2019.pdf` (Art. 12.º + transitional provisions) | Authenticity and integrity of electronic invoices: qualified signature, qualified seal (eIDAS 910/2014), or EDI under the "Acordo tipo EDI europeu" (Rec. 1994/820/CE); Art. 13.º adds e-invoicing software requirements (chronological validation, non-repudiation, non-duplication, certificate-revocation checks) | Phase 3 | ✅ (same file as above) |
| `oe-2026-extract.pdf` | State Budget 2026 provision extending the PDF transitional regime to 31 Dec 2026 | Phase 3 | |
| `eidas-910-2014.pdf` (as amended by Reg. 2024/1183) | EU definitions of qualified signatures, seals and trust service providers | Phase 3 | |
| `etsi-en-319-142.pdf` | PAdES: technical standard for signatures embedded in PDF | Phase 3 | |
| Trust service provider API docs | Remote sealing API of the chosen provider (e.g. Multicert, DigitalSign, AMA) | Phase 3 | |

## Other

| File | Content | Needed for |
|---|---|---|
| `cius-pt.pdf` | Structured e-invoice specification (B2G) | After v1 |
| `inventory-communication.pdf` | Annual inventory communication rules and format | Phase 5 (if decided) |
