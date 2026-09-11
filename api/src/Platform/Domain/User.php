<?php

declare(strict_types=1);

namespace App\Platform\Domain;

final class User
{
    private function __construct(
        private readonly UserId $id,
        private string $email,
        private string $name,
        private string $passwordHash,
        private bool $mustChangePassword,
        private ?\DateTimeImmutable $passwordChangedAt,
        private ?string $mfaSecret,
        private string $status,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(
        UserId $id,
        string $email,
        string $name,
        string $passwordHash,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            email: $email,
            name: $name,
            passwordHash: $passwordHash,
            mustChangePassword: true,
            passwordChangedAt: null,
            mfaSecret: null,
            status: 'active',
            createdAt: $now,
        );
    }

    public function changePassword(string $passwordHash, \DateTimeImmutable $now): void
    {
        $this->passwordHash = $passwordHash;
        $this->mustChangePassword = false;
        $this->passwordChangedAt = $now;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function passwordChangedAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }

    public function mfaSecret(): ?string
    {
        return $this->mfaSecret;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
