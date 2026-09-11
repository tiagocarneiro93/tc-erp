<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

final class InvalidOrExpiredResetToken extends \DomainException
{
    public function __construct()
    {
        parent::__construct('This password reset link is invalid or has expired.');
    }
}
