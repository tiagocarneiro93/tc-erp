<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrects the PT-MA (Madeira) reduced VAT rate seeded by
 * Version20260911211300 (task 1.2): that migration seeded 4%, flagged as
 * an unconfirmed candidate from a secondary source, with civa-extracts.md
 * itself noting a second secondary source disagreed and gave 5% instead.
 *
 * The owner has now confirmed all nine PT/PT-AC/PT-MA RED/INT/NOR rates
 * against the official AT portal
 * (info.portaldasfinancas.gov.pt/pt/informacao_fiscal/codigos_tributarios/civa_rep/Pages/c-iva-listas.aspx,
 * pasted 2026-09-20, this sandbox's network egress to that domain is
 * blocked): mainland 6/13/23, Açores 4/9/16, Madeira 5/12/22. Eight of the
 * nine already matched the seeded candidate; only PT-MA/RED was wrong
 * (4%, not 5%) — this migration fixes that one value and drops the
 * "unconfirmed" wording from every PT-AC/PT-MA row's description, since
 * all nine are now sourced from the AT itself, not a secondary table.
 *
 * Never edits the committed Version20260911211300 (CLAUDE.md) — this is a
 * correction migration, not a new legally-effective rate (the 4% value
 * was never actually in force; it was our own seeding error), so it
 * updates the existing row in place rather than adding a new
 * `valid_from`-dated one.
 */
final class Version20260920090000 extends AbstractMigration
{
    private const CONFIRMED_DESCRIPTIONS = [
        // region => code => description
        'PT-AC' => [
            'RED' => 'Taxa reduzida (Açores) — Lei Orgânica n.º 2/2013, confirmado no Portal das Finanças',
            'INT' => 'Taxa intermédia (Açores) — Lei Orgânica n.º 2/2013, confirmado no Portal das Finanças',
            'NOR' => 'Taxa normal (Açores) — Lei Orgânica n.º 2/2013, confirmado no Portal das Finanças',
        ],
        'PT-MA' => [
            'RED' => 'Taxa reduzida (Madeira) — Lei Orgânica n.º 2/2013, confirmado no Portal das Finanças',
            'INT' => 'Taxa intermédia (Madeira) — Lei Orgânica n.º 2/2013, confirmado no Portal das Finanças',
            'NOR' => 'Taxa normal (Madeira) — Lei Orgânica n.º 2/2013, confirmado no Portal das Finanças',
        ],
    ];

    public function getDescription(): string
    {
        return 'Correct PT-MA reduced VAT rate to 5% and confirm PT-AC/PT-MA rates against the AT portal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE tax_rates SET percentage = '5.00' WHERE region = 'PT-MA' AND code = 'RED' AND valid_from = '2011-01-01'",
        );

        foreach (self::CONFIRMED_DESCRIPTIONS as $region => $rates) {
            foreach ($rates as $code => $description) {
                $this->addSql(
                    'UPDATE tax_rates SET description = :description WHERE region = :region AND code = :code AND valid_from = :valid_from',
                    ['description' => $description, 'region' => $region, 'code' => $code, 'valid_from' => '2011-01-01'],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE tax_rates SET percentage = '4.00', description = 'Taxa reduzida (Madeira) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md' WHERE region = 'PT-MA' AND code = 'RED' AND valid_from = '2011-01-01'",
        );
        $this->addSql("UPDATE tax_rates SET description = 'Taxa reduzida (Açores) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md' WHERE region = 'PT-AC' AND code = 'RED' AND valid_from = '2011-01-01'");
        $this->addSql("UPDATE tax_rates SET description = 'Taxa intermédia (Açores) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md' WHERE region = 'PT-AC' AND code = 'INT' AND valid_from = '2011-01-01'");
        $this->addSql("UPDATE tax_rates SET description = 'Taxa normal (Açores) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md' WHERE region = 'PT-AC' AND code = 'NOR' AND valid_from = '2011-01-01'");
        $this->addSql("UPDATE tax_rates SET description = 'Taxa intermédia (Madeira) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md' WHERE region = 'PT-MA' AND code = 'INT' AND valid_from = '2011-01-01'");
        $this->addSql("UPDATE tax_rates SET description = 'Taxa normal (Madeira) — valor não confirmado contra o decreto legislativo regional, ver docs/legal/civa-extracts.md' WHERE region = 'PT-MA' AND code = 'NOR' AND valid_from = '2011-01-01'");
    }
}
