<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * No recipient was given and the document's customer has no e-mail address.
 */
final class NoEmailRecipient extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No recipient was given and the customer has no e-mail address.');
    }

    public function problemType(): string
    {
        return 'no-email-recipient';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
