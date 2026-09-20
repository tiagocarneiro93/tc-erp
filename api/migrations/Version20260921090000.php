<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Corrects PT-MA (Madeira)'s reduced VAT rate again — Version20260920090000
 * (never edited here, per CLAUDE.md) blanket-confirmed all nine mainland/
 * Açores/Madeira rates as a single eternal value each, including changing
 * PT-MA/RED from a 4% candidate to 5%, based on the AT portal
 * (info.portaldasfinancas.gov.pt). The owner has since identified that
 * page as stale: Madeira's reduced rate has been **4%** since **1 October
 * 2024**, per Decreto Legislativo Regional n.º 6/2024/M, de 29 de julho,
 * art. 21.º (owner-cited; the decree's own text is not yet in
 * `docs/legal/` — still worth obtaining for a complete primary-source
 * citation). 5% was the rate immediately before that change.
 *
 * This is therefore not a data-entry error like the last correction was —
 * it's a genuine rate change effective on a specific date, exactly what
 * `tax_rates`' `valid_from`/`valid_to` versioning exists for (§6.5). The
 * single 2011-01-01 row is split into two: the historical 5% rate now
 * ends 2024-09-30, and a new 4% row starts 2024-10-01 with no end date.
 *
 * The pre-2024-10-01 value (5%) is *not* independently confirmed against
 * the decree's own predecessor-rate text — it's inferred from the AT
 * portal figure being a plausible pre-change value, not a primary source
 * for that specific historical period. Flagged as such below; 🧑 owner
 * confirmation or the decree text itself would close this.
 *
 * PT-MA's `INT`/`NOR` rates and PT-AC's rates are untouched — the owner's
 * correction named only PT-MA `RED` — but the same "AT portal may be
 * stale" risk applies to them and is worth a second look.
 */
final class Version20260921090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split PT-MA reduced VAT rate into pre/post 1 Oct 2024 (4%, DLR 6/2024/M art. 21)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE tax_rates SET percentage = '5.00', valid_to = '2024-09-30', description = :description WHERE region = 'PT-MA' AND code = 'RED' AND valid_from = '2011-01-01'",
            ['description' => 'Taxa reduzida (Madeira), até 2024-09-30 — valor anterior à alteração pela DLR 6/2024/M; não confirmado de forma independente contra o texto anterior do diploma, ver docs/legal/civa-extracts.md'],
        );

        $this->addSql(
            'INSERT INTO tax_rates (id, region, code, percentage, valid_from, valid_to, description) VALUES (:id, :region, :code, :percentage, :valid_from, :valid_to, :description)',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'region' => 'PT-MA',
                'code' => 'RED',
                'percentage' => '4.00',
                'valid_from' => '2024-10-01',
                'valid_to' => null,
                'description' => 'Taxa reduzida (Madeira), desde 2024-10-01 — Decreto Legislativo Regional n.º 6/2024/M, de 29 de julho, art. 21.º',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM tax_rates WHERE region = 'PT-MA' AND code = 'RED' AND valid_from = '2024-10-01'");
        $this->addSql(
            "UPDATE tax_rates SET percentage = '5.00', valid_to = NULL, description = :description WHERE region = 'PT-MA' AND code = 'RED' AND valid_from = '2011-01-01'",
            ['description' => 'Taxa reduzida (Madeira) — confirmado no Portal das Finanças'],
        );
    }
}
