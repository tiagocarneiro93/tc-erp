<?php

declare(strict_types=1);

namespace App\Output\Application;

use App\Output\Domain\InvalidStoredFileKind;
use App\Output\Domain\ObjectStorage;
use App\Output\Domain\StoredFile;
use App\Output\Domain\StoredFileIntegrityViolation;
use App\Output\Domain\StoredFileKind;
use App\Output\Domain\StoredFileNotFound;
use App\Output\Domain\StoredFileRepository;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Output\ArchivedFile;
use App\Shared\Domain\Output\FileArchive;
use Symfony\Component\Uid\Uuid;

/**
 * docs/plans/phase-3.md task 3.4: the archive. Object first, row second: the
 * bytes are uploaded before the `stored_files` row is written, so a row never
 * points at an object that was not stored (a crash in between leaves an
 * unreferenced object, which is harmless). The SHA-256 is computed from the
 * bytes handed in and recorded; every read recomputes it and refuses to serve
 * bytes that no longer match — a hard failure, never a silent one.
 *
 * Keys are `<company>/<kind>/<year>/<file id>`: a company prefix as defence in
 * depth, though the real isolation is that a file can only be reached through
 * its RLS-protected `stored_files` row.
 */
final class StoredFiles implements FileArchive
{
    public function __construct(
        private readonly StoredFileRepository $files,
        private readonly ObjectStorage $storage,
        private readonly Clock $clock,
    ) {
    }

    public function storeFile(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, string $sourcePath): ArchivedFile
    {
        $sha256 = hash_file('sha256', $sourcePath);
        $size = filesize($sourcePath);
        $stream = fopen($sourcePath, 'r');

        if (false === $sha256 || false === $size || false === $stream) {
            throw new \RuntimeException('The file to archive could not be read.');
        }

        try {
            return $this->archive($companyId, $this->kind($kind), $subjectType, $subjectId, $stream, $sha256, $size);
        } finally {
            fclose($stream);
        }
    }

    public function storeContents(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, string $contents): ArchivedFile
    {
        $stream = fopen('php://memory', 'r+');

        if (false === $stream) {
            throw new \RuntimeException('Could not open an in-memory stream.');
        }

        try {
            fwrite($stream, $contents);
            rewind($stream);

            return $this->archive($companyId, $this->kind($kind), $subjectType, $subjectId, $stream, hash('sha256', $contents), \strlen($contents));
        } finally {
            fclose($stream);
        }
    }

    public function findBySubject(CompanyId $companyId, string $kind, string $subjectType, string $subjectId): ?ArchivedFile
    {
        $file = $this->files->findFirstBySubject($companyId, $this->kind($kind), $subjectType, $subjectId);

        return null === $file ? null : $this->toArchivedFile($file);
    }

    public function contents(CompanyId $companyId, string $fileId): string
    {
        $file = $this->files->find($companyId, $fileId) ?? throw new StoredFileNotFound();
        $stream = $this->storage->get($file->storageKey);

        try {
            $context = hash_init('sha256');
            $contents = '';

            while (!feof($stream)) {
                $chunk = fread($stream, 1 << 20);

                if (false === $chunk) {
                    throw new \RuntimeException('The stored file could not be read.');
                }

                hash_update($context, $chunk);
                $contents .= $chunk;
            }
        } finally {
            fclose($stream);
        }

        $this->verify($file, hash_final($context));

        return $contents;
    }

    public function copyTo(CompanyId $companyId, string $fileId, string $targetPath): void
    {
        $file = $this->files->find($companyId, $fileId) ?? throw new StoredFileNotFound();
        $source = $this->storage->get($file->storageKey);
        $target = fopen($targetPath, 'w');

        if (false === $target) {
            fclose($source);

            throw new \RuntimeException('The target file could not be opened for writing.');
        }

        try {
            $context = hash_init('sha256');

            while (!feof($source)) {
                $chunk = fread($source, 1 << 20);

                if (false === $chunk) {
                    throw new \RuntimeException('The stored file could not be read.');
                }

                hash_update($context, $chunk);
                fwrite($target, $chunk);
            }
        } finally {
            fclose($source);
            fclose($target);
        }

        try {
            $this->verify($file, hash_final($context));
        } catch (StoredFileIntegrityViolation $violation) {
            @unlink($targetPath);

            throw $violation;
        }
    }

    /**
     * @param resource $stream
     */
    private function archive(CompanyId $companyId, StoredFileKind $kind, string $subjectType, string $subjectId, $stream, string $sha256, int $size): ArchivedFile
    {
        $id = Uuid::v7()->toRfc4122();
        $now = $this->clock->now();
        $key = \sprintf('%s/%s/%s/%s', $companyId->toString(), $kind->value, $now->format('Y'), $id);

        $this->storage->put($key, $stream, $kind->contentType());

        $file = new StoredFile($id, $companyId, $kind, $subjectType, $subjectId, $key, $sha256, $size, $now);
        $this->files->add($file);

        return $this->toArchivedFile($file);
    }

    private function verify(StoredFile $file, string $actualSha256): void
    {
        if (!hash_equals($file->sha256, $actualSha256)) {
            throw new StoredFileIntegrityViolation($file, $actualSha256);
        }
    }

    private function kind(string $kind): StoredFileKind
    {
        return StoredFileKind::tryFrom($kind) ?? throw new InvalidStoredFileKind($kind);
    }

    private function toArchivedFile(StoredFile $file): ArchivedFile
    {
        return new ArchivedFile($file->id, $file->kind->value, $file->sha256, $file->size, $file->createdAt);
    }
}
