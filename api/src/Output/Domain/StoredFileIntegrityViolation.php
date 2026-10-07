<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * The bytes in object storage no longer hash to what `stored_files` recorded
 * when they were written. A hard failure on purpose — an archived fiscal
 * artifact that has silently changed must never be served as if it were the
 * original (a plain exception: generic 500, full detail to the logs/Sentry).
 */
final class StoredFileIntegrityViolation extends \RuntimeException
{
    public function __construct(StoredFile $file, string $actualSha256)
    {
        parent::__construct(\sprintf(
            'Stored file %s (%s) failed its integrity check: recorded SHA-256 %s, found %s.',
            $file->id,
            $file->storageKey,
            $file->sha256,
            $actualSha256,
        ));
    }
}
