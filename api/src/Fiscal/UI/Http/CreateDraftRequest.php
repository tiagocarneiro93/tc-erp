<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * `payload` is deliberately loosely validated here — the same raw shape
 * `POST /calculate` accepts (task 2.1), plus `series_id`/`customer_id`/
 * `references`. A draft is mutable and allowed to be incomplete; strict,
 * per-field validation happens on demand via
 * `GET .../drafts/{id}/validate` (task 2.4), not at every write.
 */
final class CreateDraftRequest
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $document_type = '',
        public readonly array $payload = [],
    ) {
    }
}
