<?php

declare(strict_types=1);

namespace App\Shared\Domain\Output;

/**
 * What a caller learns about a file it has just archived (or looked up): the
 * handle ({@see $id}) to read it back, and its recorded hash and size.
 */
final class ArchivedFile
{
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly string $sha256,
        public readonly int $size,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
