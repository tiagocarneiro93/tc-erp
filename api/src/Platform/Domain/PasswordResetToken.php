<?php

declare(strict_types=1);

namespace App\Platform\Domain;

/**
 * A single-use, time-limited password reset link (CLAUDE.md task 0.8:
 * "no endpoint ever returns or lets admins set a known password — resets
 * by email link only"). `tokenHash` is a hash of the token mailed to the
 * user; the raw token is never stored.
 */
final class PasswordResetToken
{
    private function __construct(
        private readonly PasswordResetTokenId $id,
        private readonly UserId $userId,
        private readonly string $tokenHash,
        private readonly \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $usedAt,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function issue(
        PasswordResetTokenId $id,
        UserId $userId,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            usedAt: null,
            createdAt: $now,
        );
    }

    public function isValid(\DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $now < $this->expiresAt;
    }

    public function markUsed(\DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }

    public function id(): PasswordResetTokenId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
