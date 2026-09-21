<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

/**
 * The result of {@see DocumentSigner::sign()}: the base-64 signature
 * (Despacho 8632/2014 §7.1: exactly 172 ASCII bytes for RSA-1024/SHA-1,
 * no line-break characters) together with the private key version that
 * produced it, which Despacho 8632/2014 §2.1.3 requires be recorded
 * alongside the signature itself.
 */
final class SignedHash
{
    public function __construct(
        private readonly string $hash,
        private readonly int $keyVersion,
    ) {
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function keyVersion(): int
    {
        return $this->keyVersion;
    }
}
