<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentDraftId;

final class ValidateDraft
{
    public function __construct(
        public readonly DocumentDraftId $draftId,
    ) {
    }
}
