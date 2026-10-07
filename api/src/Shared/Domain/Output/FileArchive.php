<?php

declare(strict_types=1);

namespace App\Shared\Domain\Output;

use App\Shared\Domain\CompanyId;

/**
 * Cross-module port (docs/decisions/0004's pattern) in front of Output's
 * object storage + `stored_files` register (docs/plans/phase-3.md task 3.4).
 * `$kind` is one of `sealed_pdf|saft|attachment`.
 *
 * Every method needs a company transaction (RLS on `stored_files`) — the
 * bytes themselves live in object storage, reachable only through a row the
 * company can see, so one company can never read another's file.
 */
interface FileArchive
{
    /**
     * Archives a local file, streaming it (a SAF-T file may be hundreds of
     * megabytes) while its SHA-256 is computed.
     */
    public function storeFile(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, string $sourcePath): ArchivedFile;

    public function storeContents(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, string $contents): ArchivedFile;

    /**
     * The earliest file of this kind archived for the subject, if any.
     */
    public function findBySubject(CompanyId $companyId, string $kind, string $subjectType, string $subjectId): ?ArchivedFile;

    /**
     * The file's bytes, verified against the recorded SHA-256.
     *
     * @throws \RuntimeException when the stored bytes no longer match their recorded hash
     */
    public function contents(CompanyId $companyId, string $fileId): string;

    /**
     * Streams the file to `$targetPath`, verifying its SHA-256 as it goes;
     * on a mismatch the partial target is deleted and an exception thrown.
     */
    public function copyTo(CompanyId $companyId, string $fileId, string $targetPath): void;
}
