<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Seeds tax_rates and exemption_reasons (docs/plans/phase-1.md task 1.2).
 *
 * exemption_reasons: the full M01-M99 table from
 * docs/legal/at-tabela-codigos-motivo-isencao.pdf (V4.0, 18 Jun 2026).
 * `valid_from` follows that document's own history: the base table applies
 * "a partir de julho de 2022"; M26/M34 were added in V2.0 (14-04-2023);
 * M44/M45/M46 in V3.0 (14-08-2025); M35 in V4.0, whose own wording says it
 * "aplica-se a faturas emitidas após 1 de julho de 2026" (used verbatim
 * instead of the V4.0 publication date, since it's more precise).
 *
 * tax_rates: mainland (RED 6%, INT 13%, NOR 23%) per
 * docs/legal/civa-extracts.md, CIVA art. 18.º n.º 1 (Redação da Lei
 * n.º 55-A/2010, de 31 de dezembro) -- valid_from uses that law's own
 * effective date (1 Jan 2011, Orçamento do Estado 2011), not the
 * publication date. Açores and Madeira rows use the owner-supplied
 * candidate value from docs/legal/civa-extracts.md's "Still open" section
 * -- NOT the regional decree itself -- flagged as such in `description`
 * and pending 🧑 owner confirmation before Phase 2 relies on them
 * (docs/plans/phase-1.md task 1.2 acceptance).
 */
final class Version20260911211300 extends AbstractMigration
{
    private const EXEMPTION_REASONS = [
        // code => [description, legal_reference, valid_from]
        'M01' => ['Artigo 16.º n.º 6 do CIVA', 'Artigo 16.º n.º 6 alíneas a) a d) do CIVA', '2022-07-01'],
        'M02' => ['Artigo 6.º do Decreto-Lei n.º 198/90, de 19 de junho', 'Artigo 6.º do Decreto-Lei n.º 198/90, de 19 de junho', '2022-07-01'],
        'M04' => ['Isento artigo 13.º do CIVA', 'Artigo 13.º do CIVA', '2022-07-01'],
        'M05' => ['Isento artigo 14.º do CIVA', 'Artigo 14.º do CIVA', '2022-07-01'],
        'M06' => ['Isento artigo 15.º do CIVA', 'Artigo 15.º do CIVA', '2022-07-01'],
        'M07' => ['Isento artigo 9.º do CIVA', 'Artigo 9.º do CIVA', '2022-07-01'],
        'M09' => ['IVA – não confere direito a dedução', 'Artigo 62.º alínea b) do CIVA', '2022-07-01'],
        'M10' => ['IVA – regime de isenção', 'Artigo 53.º n.º 1 do CIVA', '2022-07-01'],
        'M11' => ['Regime particular do tabaco', 'Decreto-Lei n.º 346/85, de 23 de agosto', '2022-07-01'],
        'M12' => ['Regime da margem de lucro – Agências de viagens', 'Decreto-Lei n.º 221/85, de 3 de julho', '2022-07-01'],
        'M13' => ['Regime da margem de lucro – Bens em segunda mão', 'Decreto-Lei n.º 199/96, de 18 de outubro', '2022-07-01'],
        'M14' => ['Regime da margem de lucro – Objetos de arte', 'Decreto-Lei n.º 199/96, de 18 de outubro', '2022-07-01'],
        'M15' => ['Regime da margem de lucro – Objetos de coleção e antiguidades', 'Decreto-Lei n.º 199/96, de 18 de outubro', '2022-07-01'],
        'M16' => ['Isento artigo 14.º do RITI', 'Artigo 14.º do RITI', '2022-07-01'],
        'M19' => ['Outras isenções', 'Isenções temporárias determinadas em diploma próprio', '2022-07-01'],
        'M20' => ['IVA – regime forfetário', 'Artigo 59.º-D n.º 2 do CIVA', '2022-07-01'],
        'M21' => ['IVA – não confere direito à dedução (ou expressão similar)', 'Artigo 72.º n.º 4 do CIVA', '2022-07-01'],
        'M25' => ['Mercadorias à consignação', 'Artigo 38.º n.º 1 alínea a) do CIVA', '2022-07-01'],
        'M26' => ['Isenção de IVA com direito à dedução no cabaz alimentar', 'Lei n.º 17/2023, de 14 de abril', '2023-04-14'],
        'M30' => ['IVA - autoliquidação', 'Artigo 2.º n.º 1 alínea i) do CIVA', '2022-07-01'],
        'M31' => ['IVA - autoliquidação', 'Artigo 2.º n.º 1 alínea j) do CIVA. A utilizar na comunicação de faturas à AT em que se verifiquem as condições para a aplicação da regra de inversão do sujeito passivo nas aquisições de serviços de construção civil, em regime de empreitada ou subempreitada, por sujeitos passivos sediados em território nacional, ou que aqui possuam estabelecimento estável ou domicílio, desde que pratiquem operações que confiram, total ou parcialmente, o direito à dedução. Na comunicação de faturas à AT relativas a empreitadas de construção ou reabilitação de imóveis abrangidas pelo Decreto-Lei n.º 97/2026, de 20 de maio, deverá ser utilizado o código M35 - IVA - Autoliquidação – Artigo 2.º, n.º 1, alínea j) do CIVA – Verba 2.42 da Lista I.', '2022-07-01'],
        'M32' => ['IVA - autoliquidação', 'Artigo 2.º n.º 1 alínea l) do CIVA', '2022-07-01'],
        'M33' => ['IVA - autoliquidação', 'Artigo 2.º n.º 1 alínea m) do CIVA', '2022-07-01'],
        'M34' => ['IVA - autoliquidação', 'Artigo 2.º n.º 1 alínea n) do CIVA', '2023-04-14'],
        'M35' => ['IVA - autoliquidação', 'Artigo 2.º n.º 1 alínea j) do CIVA – Verba 2.42 da Lista I. A utilizar na comunicação de faturas à AT em que se verifiquem as condições para a aplicação da regra de inversão do sujeito, relativas a empreitadas de construção ou reabilitação de imóveis destinadas à venda para habitação própria e permanente do adquirente ou ao arrendamento habitacional, nas condições previstas no Decreto-Lei n.º 97/2026, de 20 de maio.', '2026-07-01'],
        'M40' => ['IVA - autoliquidação', 'Artigo 6.º n.º 6 alínea a) do CIVA, a contrário', '2022-07-01'],
        'M41' => ['IVA - autoliquidação', 'Artigo 8.º n.º 3 do RITI', '2022-07-01'],
        'M42' => ['IVA - autoliquidação', 'Decreto-Lei n.º 21/2007, de 29 de janeiro', '2022-07-01'],
        'M43' => ['IVA - autoliquidação', 'Decreto-Lei n.º 362/99, de 16 de setembro', '2022-07-01'],
        'M44' => ['IVA – Regras específicas - artigo 6.º', 'Artigo 6.º do CIVA – Regras específicas. A utilizar nas operações que não sejam localizadas em Portugal por força das regras de exceção constantes dos números 7 e seguintes do artigo 6.º do Código do IVA.', '2025-08-14'],
        'M45' => ['IVA – regime transfronteiriço de isenção', 'Artigo 58.º-A do CIVA. A utilizar nas operações localizadas noutro Estado Membro da União Europeia e que ali fiquem isentas de IVA, em virtude de o transmitente dos bens ou prestador dos serviços ter aderido ao Regime Transfronteiriço de Isenção relativamente às operações que realize nesse Estado Membro.', '2025-08-14'],
        'M46' => ['IVA – e-TaxFree', 'Decreto-Lei n.º 19/2017, de 14 de fevereiro. A utilizar pelo vendedor na emissão de faturas relativas a operações em que tenha aplicado a isenção na transmissão de bens a serem transportados na bagagem pessoal de viajantes sem domicílio ou estabelecimento na União Europeia, nos termos do referido decreto-lei.', '2025-08-14'],
        'M99' => ['Não sujeito ou não tributado', 'Outras situações de não liquidação do imposto (Exemplos: artigo 2.º n.º 2; artigo 3.º n.ºs 4, 6 e 7; artigo 4.º n.º 5, todos do CIVA).', '2022-07-01'],
    ];

    private const TAX_RATES = [
        // region => [code => [percentage, description]]
        'PT' => [
            'RED' => ['6.00', 'Taxa reduzida (Continente) — Lista I'],
            'INT' => ['13.00', 'Taxa intermédia (Continente) — Lista II'],
            'NOR' => ['23.00', 'Taxa normal (Continente)'],
        ],
        'PT-AC' => [
            'RED' => ['4.00', 'Taxa reduzida (Açores) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md'],
            'INT' => ['9.00', 'Taxa intermédia (Açores) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md'],
            'NOR' => ['16.00', 'Taxa normal (Açores) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md'],
        ],
        'PT-MA' => [
            'RED' => ['4.00', 'Taxa reduzida (Madeira) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md'],
            'INT' => ['12.00', 'Taxa intermédia (Madeira) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md'],
            'NOR' => ['22.00', 'Taxa normal (Madeira) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md'],
        ],
    ];

    public function getDescription(): string
    {
        return 'Seed tax_rates and exemption_reasons (docs/plans/phase-1.md task 1.2)';
    }

    public function up(Schema $schema): void
    {
        foreach (self::EXEMPTION_REASONS as $code => [$description, $legalReference, $validFrom]) {
            $this->addSql(
                'INSERT INTO exemption_reasons (code, description, legal_reference, valid_from) VALUES (:code, :description, :legal_reference, :valid_from) ON CONFLICT (code) DO NOTHING',
                ['code' => $code, 'description' => $description, 'legal_reference' => $legalReference, 'valid_from' => $validFrom],
            );
        }

        $mainlandEffectiveDate = '2011-01-01';
        $unconfirmedRegionalEffectiveDate = '2011-01-01';

        foreach (self::TAX_RATES as $region => $rates) {
            $validFrom = 'PT' === $region ? $mainlandEffectiveDate : $unconfirmedRegionalEffectiveDate;

            foreach ($rates as $code => [$percentage, $description]) {
                $this->addSql(
                    'INSERT INTO tax_rates (id, region, code, percentage, valid_from, description) VALUES (:id, :region, :code, :percentage, :valid_from, :description) ON CONFLICT (region, code, valid_from) DO NOTHING',
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'region' => $region,
                        'code' => $code,
                        'percentage' => $percentage,
                        'valid_from' => $validFrom,
                        'description' => $description,
                    ],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM tax_rates');
        $this->addSql('DELETE FROM exemption_reasons');
    }
}
