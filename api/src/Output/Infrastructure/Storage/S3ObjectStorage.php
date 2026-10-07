<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Storage;

use App\Output\Domain\ObjectNotFound;
use App\Output\Domain\ObjectStorage;
use AsyncAws\S3\Exception\NoSuchKeyException;
use AsyncAws\S3\S3Client;

/**
 * S3-compatible object storage — MinIO in development and test
 * (`docker-compose.yml`), the provider's S3 API in production. Bucket-level
 * policy (versioning, object lock/WORM for the 10-year retention of DL 28/2019
 * Art. 27.º, lifecycle rules) is infrastructure, configured where the bucket
 * is created (technical-scope.md §14.3 item 23), not here.
 */
final class S3ObjectStorage implements ObjectStorage
{
    private bool $bucketEnsured = false;

    public function __construct(
        private readonly S3Client $client,
        private readonly string $bucket,
        private readonly bool $autoCreateBucket,
    ) {
    }

    public function put(string $key, $stream, string $contentType): void
    {
        $this->ensureBucket();

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $stream,
            'ContentType' => $contentType,
        ])->resolve();
    }

    public function get(string $key)
    {
        try {
            $result = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $key]);

            return $result->getBody()->getContentAsResource();
        } catch (NoSuchKeyException) {
            throw new ObjectNotFound($key);
        }
    }

    public function exists(string $key): bool
    {
        return $this->client->objectExists(['Bucket' => $this->bucket, 'Key' => $key])->isSuccess();
    }

    /**
     * Development/test convenience (`S3_AUTO_CREATE_BUCKET=1`): production
     * buckets are created by whoever owns the storage, with their retention
     * settings, and the application is not given the right to create them.
     */
    private function ensureBucket(): void
    {
        if ($this->bucketEnsured || !$this->autoCreateBucket) {
            return;
        }

        if (!$this->client->bucketExists(['Bucket' => $this->bucket])->isSuccess()) {
            $this->client->createBucket(['Bucket' => $this->bucket])->resolve();
        }

        $this->bucketEnsured = true;
    }
}
