# CIVA extracts

Source: https://info.portaldasfinancas.gov.pt/pt/informacao_fiscal/codigos_tributarios/civa_rep/pages/iva18.aspx
(Código do IVA, Artigo 18.º — Taxas do imposto). Pasted by the owner on 2026-09-11;
this sandbox's network egress is blocked for `portaldasfinancas.gov.pt`, so Claude
Code could not fetch it directly.

**Regional (Açores/Madeira) rates confirmed 2026-09-20** against the AT's own
summary page (https://info.portaldasfinancas.gov.pt/pt/informacao_fiscal/codigos_tributarios/civa_rep/Pages/c-iva-listas.aspx,
pasted by the owner — same domain as Art. 18.º above, blocked for direct fetch):
mainland 6%/13%/23%, Açores 4%/9%/16%, **Madeira 5%/12%/22%**. This resolved the
dispute noted below in "Still open" in favour of 5% for Madeira's reduced rate
(the seeded candidate had been 4%) — corrected via
`api/migrations/Version20260920090000.php` (never editing the committed
original seed).

**Correction, 2026-09-20 (later same day): the AT portal figure above is stale
for Madeira's reduced rate.** Madeira's reduced rate has been **4%** since
**1 October 2024**, per Decreto Legislativo Regional n.º 6/2024/M, de 29 de
julho, art. 21.º (owner-cited; the decree's own text is not yet in this
directory — still worth obtaining for a complete primary-source citation). 5%
was the rate immediately before that change, so this is not a data-entry
error like the same-day correction above — it's a genuine rate change
effective on a specific date. Corrected via
`api/migrations/Version20260921090000.php`, which splits the single eternal
PT-MA/RED row into two date-versioned rows (5% until 2024-09-30, 4% from
2024-10-01). The pre-2024-10-01 value of 5% is *not* independently confirmed
against the decree's own predecessor-rate text — it's inferred from the AT
portal figure being a plausible pre-change value, not a primary source for
that specific historical period. 🧑 Owner confirmation, or the decree text
itself, would close this. PT-MA's `INT`/`NOR` rates and all of PT-AC's rates
are believed unaffected (the owner's correction named only PT-MA `RED`), but
the same "AT portal may be stale" risk applies to them too.

## Article 18 — Tax rates (verbatim)

> Artigo 18.º
> Taxas do imposto
>
> 1 - As taxas do imposto são as seguintes:
>
> a) Para as importações, transmissões de bens e prestações de serviços constantes
> da lista I anexa a este diploma, a taxa de 6%;
>
> b) Para as importações, transmissões de bens e prestações de serviços constantes
> da lista II anexa a este diploma, a taxa de 13%;
>
> c) Para as restantes importações, transmissões de bens e prestações de serviços,
> a taxa de 23%. (Redação da Lei n.º 55-A/2010, de 31 de dezembro)
>
> 2 - Estão sujeitas à taxa a que se refere a alínea a) do n.º 1 as importações e
> transmissões de objectos de arte previstas em legislação especial.
>
> 3 - As Assembleias Legislativas das Regiões Autónomas dos Açores e da Madeira
> podem, nos termos previstos na Lei das Finanças das Regiões Autónomas, aprovada
> pela Lei Orgânica n.º 2/2013, de 2 de setembro, fixar taxas diminuídas do IVA
> aplicáveis às transmissões de bens e prestações de serviços que se considerem
> efetuadas nas regiões autónomas e às importações cujo desembaraço alfandegário
> tenha lugar nessas mesmas regiões. (Redação da Lei n.º 12/2022, de 27 de junho)
>
> 4 - Nas transmissões de bens constituídos pelo agrupamento de várias mercadorias,
> formando um produto comercial distinto, aplicam-se as seguintes taxas:
>
> a) Quando as mercadorias que compõem a unidade de venda não sofram alterações da
> sua natureza nem percam a sua individualidade, a taxa aplicável ao valor global
> das mercadorias é a que lhes corresponder ou, se lhes couberem taxas diferentes,
> a mais elevada;
>
> b) Quando as mercadorias que compõem a unidade de venda sofram alterações da sua
> natureza e qualidade ou percam a sua individualidade, a taxa aplicável ao
> conjunto é a que, como tal, lhe corresponder. (Redação da Lei n.º 82-B/2014, de
> 31 de dezembro)
>
> 5 - Nas prestações de serviços respeitantes a contratos de locação financeira, o
> imposto é aplicado com a mesma taxa que seria aplicável no caso de transmissão
> dos bens dados em locação financeira.
>
> 6 - Revogado. (Redação do Decreto-Lei n.º 33/2025, de 24 de março)
>
> 7 - Sem prejuízo do disposto na verba 2.1. da Lista I anexa ao presente Código,
> às prestações de serviços por via eletrónica, nomeadamente as descritas no anexo
> D, aplica-se a taxa referida na alínea c) do n.º 1. (Redação da Lei n.º 71/2018,
> de 31 de dezembro)
>
> 8 - Às importações de bens a que seja aplicável o regime de declaração e
> pagamento do IVA referido nos n.os 10 e 11 do artigo 28.º, bem como, quando não
> isentas ao abrigo do artigo 13.º ou de outros diplomas, às importações de
> mercadorias que sejam objeto de pequenas remessas enviadas a particulares ou que
> sejam contidas nas bagagens pessoais dos viajantes, sujeitas ao direito aduaneiro
> forfetário previsto nas disposições preliminares da Pauta Aduaneira Comum,
> aplica-se a taxa referida na alínea c) do n.º 1, independentemente da sua
> natureza. (Redação da Lei n.º 47/2020, de 24 de agosto)
>
> 9 - A taxa aplicável é a que vigora no momento em que o imposto se torna
> exigível.
>
> 10 - Às transmissões de objetos de arte e de coleção ou de antiguidades sujeitas
> ao regime especial de tributação dos bens em segunda mão, objetos de arte, de
> coleção e antiguidades, aplica-se a taxa referida na alínea c) do n.º 1.
> (Redação do Decreto-Lei n.º 33/2025, de 24 de março)

## Extracted rates

| Region | Code (SAF-T `TaxCode`) | Rate | Basis |
|---|---|---|---|
| Mainland (Continente) | `RED` (reduzida) | 6% | Art. 18.º n.º 1 a), Lista I |
| Mainland (Continente) | `INT` (intermédia) | 13% | Art. 18.º n.º 1 b), Lista II |
| Mainland (Continente) | `NOR` (normal) | 23% | Art. 18.º n.º 1 c) |
| Açores (PT-AC) | `RED` (reduzida) | ✅ 4% | AT portal, confirmed 2026-09-20 |
| Açores (PT-AC) | `INT` (intermédia) | ✅ 9% | AT portal, confirmed 2026-09-20 |
| Açores (PT-AC) | `NOR` (normal) | ✅ 16% | AT portal, confirmed 2026-09-20 |
| Madeira (PT-MA) | `RED` (reduzida), until 2024-09-30 | ✅ 5% | AT portal figure, not independently confirmed for this historical period — see correction note above |
| Madeira (PT-MA) | `RED` (reduzida), from 2024-10-01 | ✅ 4% | DLR 6/2024/M, de 29 de julho, art. 21.º — owner-cited, decree text not yet in this directory |
| Madeira (PT-MA) | `INT` (intermédia) | ✅ 12% | AT portal, confirmed 2026-09-20 |
| Madeira (PT-MA) | `NOR` (normal) | ✅ 22% | AT portal, confirmed 2026-09-20 |

## Resolved (previously "Still open")

Article 18 §3 only says the Regions **may** set their own reduced rates under the
Regional Finance Law (Lei Orgânica n.º 2/2013) — it doesn't itself state the
figures; those came from the AT's own summary page (see the note at the top of
this file), not the regional decree directly, but that's now an AT-portal
citation rather than a third-party table. All nine mainland/Açores/Madeira
RED/INT/NOR rates are confirmed; no more `[VERIFY]` on tax rates for Phase 2.

## Article 29 — General obligations (verbatim, relevant paragraphs)

Source: https://info.portaldasfinancas.gov.pt/pt/informacao_fiscal/codigos_tributarios/civa_rep/Pages/iva29.aspx.
Pasted by the owner on 2026-09-20; same network-egress limitation as Art. 18.º
above. Full article pasted; only §7 (the one this repo needed) and its
immediate context are reproduced here — the rest of the article (declaration
deadlines, IRC/IRS annexes, exemption thresholds, etc.) is Phase 3+/accounting
territory, not needed for Phase 2's document lifecycle.

> Artigo 29.º
> Obrigações em geral
>
> [...]
>
> 7 - Quando o valor tributável de uma operação ou o imposto correspondente
> sejam alterados por qualquer motivo, incluindo inexatidão, deve ser emitido
> documento retificativo de fatura. (Redacção do D.L. nº 197/2012, de 24 de
> Agosto, com entrada em vigor em 1 de Janeiro de 2013)
>
> [...]
>
> 19 - Não é permitida aos sujeitos passivos a emissão e entrega de documentos
> de natureza diferente da fatura para titular a transmissão de bens ou
> prestação de serviços aos respetivos adquirentes ou destinatários, sob pena
> de aplicação das penalidades legalmente previstas. (Aditado pelo D.L.
> nº 197/2012, de 24 de Agosto, com entrada em vigor em 1 de Janeiro de 2013)

### What this resolves for Phase 2 task 2.10 (cancellation)

§7 is unconditional and broad: **any** change to an invoice's taxable value
or tax amount — "por qualquer motivo, incluindo inexatidão" (for any reason,
including inaccuracy) — must be corrected through a rectifying document
(NC/ND), never by voiding the original. This is stronger than what Despacho
8632/2014 alone implied: it means "cancel and reissue" is never the
correction mechanism once a document has left the issuer's hands, full stop
— a credit/debit note always is, even to fix an outright mistake.

This sharpens (rather than replaces) the provisional rule already in
`docs/plans/phase-2.md`: true cancellation (SAF-T `InvoiceStatus = A`) is
only legitimate for a document that never had external effect — one that
was signed and numbered but never actually delivered to or seen by the
customer (e.g. a duplicate created by a client crash, a document generated
in error and caught immediately). Once a document could plausibly have
reached the customer, §7 requires a rectifying document instead. "Not yet
communicated to the AT" remains the system's own technical proxy for "not
yet delivered" — CIVA doesn't name AT communication as the legal boundary
itself, but it's the most conservative signal this system can actually
observe, and erring toward requiring a credit note over an improper
cancellation is the safer default either way.
