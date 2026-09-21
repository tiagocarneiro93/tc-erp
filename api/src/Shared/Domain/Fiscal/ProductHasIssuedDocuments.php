<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

use App\Shared\Domain\CompanyId;

/**
 * See {@see CustomerHasIssuedDocuments}'s docblock — same reasoning, for
 * Catalog's `products.description` (Despacho 8632/2014 §3.3.3–3.3.5).
 */
interface ProductHasIssuedDocuments
{
    public function forProduct(CompanyId $companyId, string $productId): bool;
}
