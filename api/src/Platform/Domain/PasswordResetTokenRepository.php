<?php

declare(strict_types=1);

namespace App\Platform\Domain;

interface PasswordResetTokenRepository
{
    public function findByTokenHash(string $tokenHash): ?PasswordResetToken;

    public function save(PasswordResetToken $token): void;
}
