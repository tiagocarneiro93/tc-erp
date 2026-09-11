<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

final class RequestPasswordReset
{
    public function __construct(public readonly string $email)
    {
    }
}
