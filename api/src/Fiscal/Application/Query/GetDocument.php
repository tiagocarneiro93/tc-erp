<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentId;

final class GetDocument
{
    public function __construct(public readonly DocumentId $documentId)
    {
    }
}
