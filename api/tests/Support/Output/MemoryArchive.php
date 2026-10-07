<?php

declare(strict_types=1);

namespace App\Tests\Support\Output;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Output\ArchivedFile;
use App\Shared\Domain\Output\FileArchive;

final class MemoryArchive implements FileArchive
{
    /** @var array<string, array{file: ArchivedFile, subject: string, contents: string}> */
    public array $files = [];

    public function storeFile(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, string $sourcePath): ArchivedFile
    {
        return $this->storeContents($companyId, $kind, $subjectType, $subjectId, (string) file_get_contents($sourcePath));
    }

    public function storeContents(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, string $contents): ArchivedFile
    {
        $id = 'file-'.(\count($this->files) + 1);
        $file = new ArchivedFile($id, $kind, hash('sha256', $contents), \strlen($contents), new \DateTimeImmutable('2026-10-07T10:00:00+00:00'));
        $this->files[$id] = ['file' => $file, 'subject' => $kind.'|'.$subjectType.'|'.$subjectId, 'contents' => $contents];

        return $file;
    }

    public function findBySubject(CompanyId $companyId, string $kind, string $subjectType, string $subjectId): ?ArchivedFile
    {
        foreach ($this->files as $entry) {
            if ($entry['subject'] === $kind.'|'.$subjectType.'|'.$subjectId) {
                return $entry['file'];
            }
        }

        return null;
    }

    public function contents(CompanyId $companyId, string $fileId): string
    {
        return $this->files[$fileId]['contents'];
    }

    public function copyTo(CompanyId $companyId, string $fileId, string $targetPath): void
    {
        file_put_contents($targetPath, $this->contents($companyId, $fileId));
    }
}
