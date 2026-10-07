<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\CompanyId;

/**
 * Insert-only (`stored_files`): there is no update and no delete. Needs a
 * company transaction around the calls, like any company-scoped table.
 */
interface StoredFileRepository
{
    public function add(StoredFile $file): void;

    public function find(CompanyId $companyId, string $id): ?StoredFile;

    /**
     * The earliest file of this kind archived for the subject — the one that
     * counts when something is only ever supposed to be stored once (a sealed
     * PDF), should a race ever have produced two.
     */
    public function findFirstBySubject(CompanyId $companyId, StoredFileKind $kind, string $subjectType, string $subjectId): ?StoredFile;
}
