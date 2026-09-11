<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

/**
 * CLAUDE.md §8.1: "password cannot be empty".
 */
final class EmptyPassword extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Password cannot be empty.');
    }
}
