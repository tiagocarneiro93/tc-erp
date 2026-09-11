<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Never expected in normal operation (only if the encryption key rotated
 * without re-encrypting stored ciphertext, or the row was corrupted) — a
 * 500, not a client error.
 */
final class AtCredentialsDecryptionFailed extends \RuntimeException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('Could not decrypt the stored AT credentials.');
    }

    public function problemType(): string
    {
        return 'at-credentials-decryption-failed';
    }

    public function httpStatus(): int
    {
        return 500;
    }
}
