<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Persistence;

use App\Output\Domain\StoredFile;
use App\Output\Domain\StoredFileKind;
use App\Output\Domain\StoredFileRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DbalStoredFileRepository implements StoredFileRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function add(StoredFile $file): void
    {
        $this->connection->insert('stored_files', [
            'id' => $file->id,
            'company_id' => $file->companyId->toString(),
            'kind' => $file->kind->value,
            'subject_type' => $file->subjectType,
            'subject_id' => $file->subjectId,
            'storage_key' => $file->storageKey,
            'sha256' => $file->sha256,
            'size' => $file->size,
            'created_at' => $file->createdAt->format('Y-m-d H:i:sP'),
        ]);
    }

    public function find(CompanyId $companyId, string $id): ?StoredFile
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM stored_files WHERE company_id = ? AND id = ?',
            [$companyId->toString(), $id],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findFirstBySubject(CompanyId $companyId, StoredFileKind $kind, string $subjectType, string $subjectId): ?StoredFile
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM stored_files WHERE company_id = ? AND kind = ? AND subject_type = ? AND subject_id = ? ORDER BY created_at, id LIMIT 1',
            [$companyId->toString(), $kind->value, $subjectType, $subjectId],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): StoredFile
    {
        return new StoredFile(
            $this->string($row['id']),
            CompanyId::fromString($this->string($row['company_id'])),
            StoredFileKind::from($this->string($row['kind'])),
            $this->string($row['subject_type']),
            $this->string($row['subject_id']),
            $this->string($row['storage_key']),
            $this->string($row['sha256']),
            (int) $this->string($row['size']),
            new \DateTimeImmutable($this->string($row['created_at'])),
        );
    }

    private function string(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return (string) $value;
        }

        throw new \UnexpectedValueException('Expected a scalar column value.');
    }
}
