<?php

declare(strict_types=1);

namespace App\Platform\Domain;

/**
 * A separate port from {@see PasswordResetMailer} even though both send a
 * "set your password" link through the same token mechanism: inviting
 * someone to a company and a user forgetting their password are different
 * business events with different wording, and only one of them names a
 * company.
 */
interface InvitationMailer
{
    public function sendInvitation(string $email, string $companyName, string $setPasswordUrl): void;
}
