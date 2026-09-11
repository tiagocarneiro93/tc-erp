# Legal and technical sources

Claude Code must resolve every **[VERIFY]** item from these documents (citing document and section). Keep the files here, named clearly, and note the version/date downloaded. Always download the **current consolidated version** from official sources (Diário da República, Portal das Finanças, e-Fatura documentation).

**Known limitation:** this sandbox's network egress is blocked for `portaldasfinancas.gov.pt` (and possibly other government domains) — Claude Code cannot fetch these directly and needs the file uploaded instead (save the page as PDF, or paste its content).

## Certification, invoicing and SAF-T

| File (suggested name) | Content | Needed for | Status |
|---|---|---|---|
| `portaria-363-2010.pdf` | Certification of invoicing software (consolidated, with amendments) | Phase 2, 6 | |
| `despacho-8632-2014.pdf` | Technical requirements for invoicing software (signing, access control, SAF-T rules) | Phase 0 (§8.1), 2 | ✅ DR 2.ª série n.º 126, 3 Jul 2014 |
| `dl-28-2019.pdf` | Invoicing, archiving and electronic invoice rules (consolidated) | Phase 2, 3 | |
| `oficio-circulado-30213-2019.pdf` | AT administrative instructions on DL 28/2019 (incl. electronic invoices, points 14–16) | Phase 2, 3 | |
| `dl-198-2012.pdf` | Communication of invoice elements to the AT (art. 3) and goods in circulation | Phase 3, 4 | |
| `atcud-qr-portaria.pdf` + `at-qrcode-spec.pdf` | ATCUD and QR code requirements and the AT's QR code technical specification | Phase 2 | |
| `saft-pt-structure.pdf` + `saft-pt-technical-notes.pdf` + `SAFTPT1.04_01.xsd` (or current) | SAF-T (PT) structure, notes and schema | Phase 2, 3 | |
| `at-tabela-codigos-motivo-isencao.pdf` | Official table of VAT exemption/non-liquidation reason codes (M01–M99), invoice wording and legal basis per code | Phase 1, 2 | ✅ V4.0, 18 Jun 2026 |
| `civa-extracts.md` | CIVA art. 18 VAT rates (standard/intermediate/reduced) for mainland Portugal, Açores and Madeira, taxable base and discounts, invoice requirements | Phase 1, 2 | ⚠️ mainland resolved (owner pasted art. 18); Açores/Madeira's actual rates still need their own regional decree — see the file's "Still open" section |

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
| `dl-28-2019.pdf` (art. 12 + transitional provisions) | Authenticity and integrity of electronic invoices: qualified signature, qualified seal, EDI | Phase 3 | |
| `oe-2026-extract.pdf` | State Budget 2026 provision extending the PDF transitional regime to 31 Dec 2026 | Phase 3 | |
| `eidas-910-2014.pdf` (as amended by Reg. 2024/1183) | EU definitions of qualified signatures, seals and trust service providers | Phase 3 | |
| `etsi-en-319-142.pdf` | PAdES: technical standard for signatures embedded in PDF | Phase 3 | |
| Trust service provider API docs | Remote sealing API of the chosen provider (e.g. Multicert, DigitalSign, AMA) | Phase 3 | |

## Other

| File | Content | Needed for |
|---|---|---|
| `cius-pt.pdf` | Structured e-invoice specification (B2G) | After v1 |
| `inventory-communication.pdf` | Annual inventory communication rules and format | Phase 5 (if decided) |
