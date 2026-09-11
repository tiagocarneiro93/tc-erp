<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.2/§8.2: the AT "subuser" credentials used to
 * authenticate webservice calls (Phase 3) — never the taxpayer's own Portal
 * das Finanças login. `passwordEncrypted` is ciphertext produced by
 * {@see AtCredentialsEncryptor}; this entity never sees the plaintext.
 */
final class AtCredentials
{
    /**
     * `<NIF>/<sequence number>`, e.g. "555555555/55" — the format AT itself
     * uses to identify a subuser (docs/legal/at-ws-efatura-aspetos-genericos.pdf
     * §2: "obtém a identificação do subutilizador (e.g., 555555555/55)").
     * Not the check-digit-validated {@see \App\Shared\Domain\Nif}: the AT
     * document never states the suffix's digit count or range, only gives
     * this one example, so only the two-part shape is enforced here.
     */
    private const SUBUSER_FORMAT = '/^\d{9}\/\d+$/';

    private function __construct(
        private readonly CompanyId $companyId,
        private string $subuser,
        private string $passwordEncrypted,
        private ?\DateTimeImmutable $validatedAt,
        private ?string $lastError,
    ) {
    }

    public static function create(CompanyId $companyId, string $subuser, string $passwordEncrypted): self
    {
        return new self($companyId, $subuser, $passwordEncrypted, null, null);
    }

    public static function isValidSubuserFormat(string $subuser): bool
    {
        return 1 === preg_match(self::SUBUSER_FORMAT, $subuser);
    }

    /**
     * New credentials invalidate any previous test result.
     */
    public function updateCredentials(string $subuser, string $passwordEncrypted): void
    {
        $this->subuser = $subuser;
        $this->passwordEncrypted = $passwordEncrypted;
        $this->validatedAt = null;
        $this->lastError = null;
    }

    public function recordValidationSuccess(\DateTimeImmutable $now): void
    {
        $this->validatedAt = $now;
        $this->lastError = null;
    }

    public function recordValidationFailure(string $error): void
    {
        $this->lastError = $error;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function subuser(): string
    {
        return $this->subuser;
    }

    public function passwordEncrypted(): string
    {
        return $this->passwordEncrypted;
    }

    public function validatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }
}
