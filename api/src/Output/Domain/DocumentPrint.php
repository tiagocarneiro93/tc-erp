<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\CompanyId;

final class DocumentPrint
{
    public function __construct(
        public readonly string $id,
        public readonly CompanyId $companyId,
        public readonly string $documentId,
        public readonly DocumentPrintKind $kind,
        public readonly string $copyLabel,
        public readonly string $userId,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}
