<?php

declare(strict_types=1);

namespace App\Platform\Domain;

/**
 * Structure only for now (task 0.7); the signing service itself is Phase 2.
 * The private key is never stored here (technical-scope.md §8.2) — only
 * the public key, for verification.
 */
final class SigningKey
{
    public function __construct(
        private readonly int $version,
        private readonly string $publicKeyPem,
        private readonly \DateTimeImmutable $activeFrom,
        private readonly ?\DateTimeImmutable $retiredAt,
    ) {
    }

    public function version(): int
    {
        return $this->version;
    }

    public function publicKeyPem(): string
    {
        return $this->publicKeyPem;
    }

    public function activeFrom(): \DateTimeImmutable
    {
        return $this->activeFrom;
    }

    public function retiredAt(): ?\DateTimeImmutable
    {
        return $this->retiredAt;
    }
}
