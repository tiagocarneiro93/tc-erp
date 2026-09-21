<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

final class CancelDocumentRequest
{
    public function __construct(
        public readonly ?string $reason = null,
    ) {
    }
}
