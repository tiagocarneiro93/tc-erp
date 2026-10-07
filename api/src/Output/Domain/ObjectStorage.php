<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * The raw bytes-by-key store behind the archive (MinIO/S3 in every
 * environment). Knows nothing about companies, kinds or hashes — those are
 * {@see \App\Output\Application\StoredFiles}' concern.
 */
interface ObjectStorage
{
    /**
     * @param resource $stream readable stream, read from its current position
     */
    public function put(string $key, $stream, string $contentType): void;

    /**
     * @return resource a readable stream; the caller closes it
     *
     * @throws ObjectNotFound when nothing is stored under the key
     */
    public function get(string $key);

    public function exists(string $key): bool;
}
