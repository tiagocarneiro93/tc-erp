<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\ProductId;

final class ListProductComponents
{
    public function __construct(public readonly ProductId $kitProductId)
    {
    }
}
