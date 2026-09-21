<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * `allocations` is loosely validated here, same reasoning as
 * `CreateDraftRequest`'s `payload` — each entry's `document_id`/`amount`
 * is checked by {@see \App\Fiscal\Application\Command\IssueReceiptHandler}
 * against the actual document, not here.
 */
final class IssueReceiptRequest
{
    /**
     * @param list<array{document_id?: string, amount?: string}> $allocations
     */
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $series_id = '',
        public readonly ?string $customer_id = null,
        #[Assert\NotBlank]
        public readonly string $payment_method = '',
        public readonly array $allocations = [],
    ) {
    }
}
