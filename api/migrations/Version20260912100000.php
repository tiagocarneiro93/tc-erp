<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Seeds the exempt (`ISE`) tax rate at 0% for every region already seeded
 * by Version20260911211300 (task 1.2) — the original seed only covered
 * `RED`/`INT`/`NOR`. `tax_rates.code` already documents `ISE` as one of
 * the five SAF-T TaxCodes (technical-scope.md §6.5); unlike the mainland
 * vs. regional RED/INT/NOR percentages, 0% for an exemption is not a
 * legally variable figure, so this carries no [VERIFY] flag. Never edit
 * the original seed migration once committed (CLAUDE.md) — this ships as
 * an additive one instead.
 */
final class Version20260912100000 extends AbstractMigration
{
    private const REGIONS = ['PT', 'PT-AC', 'PT-MA'];

    public function getDescription(): string
    {
        return 'Seed the exempt (ISE) tax rate at 0% for every region';
    }

    public function up(Schema $schema): void
    {
        foreach (self::REGIONS as $region) {
            $this->addSql(
                'INSERT INTO tax_rates (id, region, code, percentage, valid_from, description) VALUES (:id, :region, :code, :percentage, :valid_from, :description) ON CONFLICT (region, code, valid_from) DO NOTHING',
                [
                    'id' => Uuid::v7()->toRfc4122(),
                    'region' => $region,
                    'code' => 'ISE',
                    'percentage' => '0.00',
                    'valid_from' => '2011-01-01',
                    'description' => 'Isento — requer um motivo de isenção',
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM tax_rates WHERE code = 'ISE'");
    }
}
