<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds `document_types.name` — a user-facing pt-PT label (e.g. "Fatura"
 * for FT), missing from the original task 1.3 seed (only the SAF-T code
 * itself was stored). These are the standard SAF-T PT document-type
 * names, not yet cited from a docs/legal/ source in this repo — verify
 * against the official SAF-T PT technical spec before treating them as
 * authoritative. Additive: never edits the committed
 * Version20260911212200 seed.
 *
 * The column keeps a `''` default (not just NOT NULL) precisely so that
 * seed migration's `INSERT ... ON CONFLICT (code) DO NOTHING` — which
 * never lists `name` — can still be re-applied without a NOT NULL
 * violation: Postgres validates the proposed row's constraints before
 * ON CONFLICT short-circuits it (see
 * DocumentTypesSeedMigrationTest::testReapplyingTheSeedDoesNotDuplicateRows).
 * document_types is only ever written by migrations, so this default is
 * never actually seen in practice for the 12 real codes.
 */
final class Version20260912110000 extends AbstractMigration
{
    private const NAMES = [
        'FT' => 'Fatura',
        'FS' => 'Fatura simplificada',
        'FR' => 'Fatura-recibo',
        'NC' => 'Nota de crédito',
        'ND' => 'Nota de débito',
        'RG' => 'Recibo',
        'GT' => 'Guia de transporte',
        'GR' => 'Guia de remessa',
        'GD' => 'Guia de devolução',
        'OR' => 'Orçamento',
        'PF' => 'Fatura pró-forma',
        'NE' => 'Nota de encomenda',
    ];

    public function getDescription(): string
    {
        return 'Add document_types.name (user-facing label)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE document_types ADD name VARCHAR(255) NOT NULL DEFAULT ''");

        foreach (self::NAMES as $code => $name) {
            $this->addSql(
                'UPDATE document_types SET name = :name WHERE code = :code',
                ['code' => $code, 'name' => $name],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_types DROP COLUMN name');
    }
}
