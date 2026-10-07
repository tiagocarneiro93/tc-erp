<?php

declare(strict_types=1);

namespace App\Tests\Support\Output;

use App\Output\Domain\DocumentPrint;
use App\Output\Domain\DocumentPrintKind;
use App\Output\Domain\DocumentPrintRepository;
use App\Shared\Domain\CompanyId;

final class MemoryPrints implements DocumentPrintRepository
{
    /** @var list<DocumentPrint> */
    public array $rows = [];
    /** @var list<string> */
    public array $locked = [];

    public function add(DocumentPrint $print): void
    {
        $this->rows[] = $print;
    }

    public function lockDocument(CompanyId $companyId, string $documentId): void
    {
        $this->locked[] = $documentId;
    }

    public function countFor(CompanyId $companyId, string $documentId): int
    {
        return \count($this->rows);
    }

    public function firstLabelOf(CompanyId $companyId, string $documentId, DocumentPrintKind $kind): ?string
    {
        foreach ($this->rows as $row) {
            if ($row->kind === $kind) {
                return $row->copyLabel;
            }
        }

        return null;
    }
}
