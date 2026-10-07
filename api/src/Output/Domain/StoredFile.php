<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\CompanyId;

/**
 * One row of `stored_files`: what was archived, where, and the SHA-256 of its
 * exact bytes. Immutable — a stored artifact is never edited.
 */
final class StoredFile
{
    public function __construct(
        public readonly string $id,
        public readonly CompanyId $companyId,
        public readonly StoredFileKind $kind,
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly string $storageKey,
        public readonly string $sha256,
        public readonly int $size,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
