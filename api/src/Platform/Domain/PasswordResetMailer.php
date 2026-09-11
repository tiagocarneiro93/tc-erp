<?php

declare(strict_types=1);

namespace App\Platform\Domain;

interface PasswordResetMailer
{
    public function sendResetLink(string $email, string $resetUrl): void;
}
