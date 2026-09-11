<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\CompanyId;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the company a message was dispatched for across a transport
 * boundary (technical-scope.md §5.4). Holds a plain string, not a
 * {@see CompanyId}, so the stamp stays trivially serializable for every
 * transport.
 */
final class CompanyStamp implements StampInterface
{
    public readonly string $companyId;

    public function __construct(CompanyId $companyId)
    {
        $this->companyId = $companyId->toString();
    }
}
