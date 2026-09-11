<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the twelve v1 document types (docs/plans/phase-1.md task 1.3,
 * technical-scope.md §6.6). `signed` follows §6.6's own comment verbatim
 * ("false for RG (receipts are not signed)") -- RG is the only exception,
 * every other type is signed per Despacho 8632/2014 §1.1. stock_effect
 * and account_effect are ordinary business-document modelling (not a
 * legal/AT format), derived from how each type is normally used:
 * FR (invoice-receipt) settles at issuance, so it carries no open
 * receivable; ND (debit note) adjusts a balance without moving goods;
 * OR/PF/NE (quote/proforma/order) are pre-sale documents with neither
 * effect yet.
 */
final class Version20260911212200 extends AbstractMigration
{
    private const DOCUMENT_TYPES = [
        // code => [saft_section, signed, stock_effect, account_effect, requires_at_prior_communication]
        'FT' => ['SalesInvoices', true, 'out', 'debit', false],
        'FS' => ['SalesInvoices', true, 'out', 'debit', false],
        'FR' => ['SalesInvoices', true, 'out', 'none', false],
        'NC' => ['SalesInvoices', true, 'in', 'credit', false],
        'ND' => ['SalesInvoices', true, 'none', 'debit', false],
        'RG' => ['Payments', false, 'none', 'credit', false],
        'GT' => ['MovementOfGoods', true, 'out', 'none', true],
        'GR' => ['MovementOfGoods', true, 'out', 'none', true],
        'GD' => ['MovementOfGoods', true, 'in', 'none', true],
        'OR' => ['WorkingDocuments', true, 'none', 'none', false],
        'PF' => ['WorkingDocuments', true, 'none', 'none', false],
        'NE' => ['WorkingDocuments', true, 'none', 'none', false],
    ];

    public function getDescription(): string
    {
        return 'Seed document_types (docs/plans/phase-1.md task 1.3)';
    }

    public function up(Schema $schema): void
    {
        foreach (self::DOCUMENT_TYPES as $code => [$saftSection, $signed, $stockEffect, $accountEffect, $requiresAtPriorCommunication]) {
            $this->addSql(
                <<<'SQL'
                    INSERT INTO document_types (code, saft_section, signed, stock_effect, account_effect, requires_at_prior_communication)
                    VALUES (:code, :saft_section, :signed, :stock_effect, :account_effect, :requires_at_prior_communication)
                    ON CONFLICT (code) DO NOTHING
                    SQL,
                [
                    'code' => $code,
                    'saft_section' => $saftSection,
                    'signed' => $signed,
                    'stock_effect' => $stockEffect,
                    'account_effect' => $accountEffect,
                    'requires_at_prior_communication' => $requiresAtPriorCommunication,
                ],
                [
                    'signed' => 'boolean',
                    'requires_at_prior_communication' => 'boolean',
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM document_types');
    }
}
