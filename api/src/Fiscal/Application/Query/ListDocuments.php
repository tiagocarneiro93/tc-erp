<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

final class ListDocuments
{
    public function __construct(
        public readonly ?string $documentType,
        public readonly ?string $status,
        public readonly ?string $customerId,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {
    }
}
