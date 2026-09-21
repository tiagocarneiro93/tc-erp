<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\DocumentWriter;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Raw DBAL, matching `Fiscal\Infrastructure\Persistence\Doctrine\IssuedDocumentsChecker`
 * (task 2.3) — none of these five tables have a Doctrine entity. Relies on
 * running inside the transaction {@see \App\Shared\Domain\TransactionManager}
 * already opened (same shared connection), so these inserts and the
 * series-lock update from the same request commit or roll back together.
 */
final class DoctrineDocumentWriter implements DocumentWriter
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function insert(CompanyId $companyId, array $document, array $lines, array $taxSummary, array $references, array $statusEvent): void
    {
        $company = ['company_id' => $companyId->toString()];

        // PDO's default string binding turns PHP false into '' for pgsql,
        // which Postgres then rejects as an invalid boolean literal — the
        // one boolean column here (is_training) needs an explicit type so
        // DBAL converts it correctly.
        $this->connection->insert('documents', $company + $document, ['is_training' => Types::BOOLEAN]);

        foreach ($lines as $line) {
            $this->connection->insert('document_lines', $company + $line);
        }

        foreach ($taxSummary as $entry) {
            $this->connection->insert('document_tax_summary', $company + $entry);
        }

        foreach ($references as $reference) {
            $this->connection->insert('document_references', $company + $reference);
        }

        $this->connection->insert('document_status_events', $company + $statusEvent);
    }
}
