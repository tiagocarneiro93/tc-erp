# CIVA extracts — VAT rates

Source: https://info.portaldasfinancas.gov.pt/pt/informacao_fiscal/codigos_tributarios/civa_rep/pages/iva18.aspx
(Código do IVA, Artigo 18.º — Taxas do imposto). Pasted by the owner on 2026-09-11;
this sandbox's network egress is blocked for `portaldasfinancas.gov.pt`, so Claude
Code could not fetch it directly.

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
| Açores (PT-AC) | `RED` (reduzida) | ⚠️ 4% (unverified) | secondary source, see below |
| Açores (PT-AC) | `INT` (intermédia) | ⚠️ 9% (unverified) | secondary source, see below |
| Açores (PT-AC) | `NOR` (normal) | ⚠️ 16% (unverified) | secondary source, see below |
| Madeira (PT-MA) | `RED` (reduzida) | ⚠️ 4% (unverified, disputed) | secondary source, see below — a separate web search returned 5% for this one figure |
| Madeira (PT-MA) | `INT` (intermédia) | ⚠️ 12% (unverified) | secondary source, see below |
| Madeira (PT-MA) | `NOR` (normal) | ⚠️ 22% (unverified) | secondary source, see below |

## Still open

Article 18 §3 only says the Regions **may** set their own reduced rates under the
Regional Finance Law (Lei Orgânica n.º 2/2013) — it does not state what those rates
currently are; that requires each Region's own legislative decree (a Decreto
Legislativo Regional), which is not in `docs/legal/`. Mainland rates are resolved
(6%/13%/23%, cited above from the primary CIVA text).

For Açores/Madeira, the owner supplied a screenshot of a third-party table quoting
4%/9%/16% (Açores) and 4%/12%/22% (Madeira) — "informação que encontrei online," not
the primary source. This is recorded above as a **candidate, not a confirmed value**:
a separate web search done earlier gave the same Açores figures but 5% (not 4%) for
Madeira's reduced rate — two secondary sources disagreeing on one figure is exactly
why this isn't being treated as settled. `docs/plans/phase-1.md` task 1.2 seeds these
candidate PT-AC/PT-MA values but flags them for explicit 🧑 owner sign-off (ideally
against the Jornal Oficial da Região Autónoma dos Açores / da Madeira, or the
region's finance department) before Phase 2's `PriceCalculator` relies on them for
real invoices.
