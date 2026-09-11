<?php

declare(strict_types=1);

namespace App\Platform\Domain;

/**
 * docs/legal/despacho-8632-2014.pdf §3.1.1 requires a password change on
 * first/administrator-triggered access and that the new password isn't
 * empty, but sets no minimum length or complexity itself — this is the
 * owner's own policy on top of that (docs/technical-scope.md §8.1).
 */
final class PasswordPolicy
{
    public const int MIN_LENGTH = 6;

    public const string REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{'.self::MIN_LENGTH.',}$/u';
}
