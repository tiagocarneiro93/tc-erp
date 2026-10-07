<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Persistence;

use App\Output\Domain\DocumentPrint;
use App\Output\Domain\DocumentPrintRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DbalDocumentPrintRepository implements DocumentPrintRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function add(DocumentPrint $print): void
    {
        $this->connection->insert('document_prints', [
            'id' => $print->id,
            'company_id' => $print->companyId->toString(),
            'document_id' => $print->documentId,
            'kind' => $print->kind->value,
            'copy_label' => $print->copyLabel,
            'user_id' => $print->userId,
            'occurred_at' => $print->occurredAt->format('Y-m-d H:i:sP'),
        ]);
    }

    public function lockDocument(CompanyId $companyId, string $documentId): void
    {
        $this->connection->fetchOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$companyId->toString().':'.$documentId]);
    }

    public function countFor(CompanyId $companyId, string $documentId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT count(*) FROM document_prints WHERE company_id = ? AND document_id = ?',
            [$companyId->toString(), $documentId],
        );

        return is_numeric($count) ? (int) $count : 0;
    }
}
