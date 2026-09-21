<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

use App\Shared\Domain\CompanyId;

/**
 * Same reasoning as {@see CustomerSnapshotProvider}: `document_lines.product_code`/
 * `product_description`/`product_type`/`unit_code` (technical-scope.md
 * §6.6) are captured fresh from the catalog at issuance, never trusted
 * from a possibly-stale draft line — this is exactly why Despacho
 * 8632/2014 §3.3.5 locks `Product::description` once referenced by an
 * issued document (task 2.3): the snapshot this port returns is what gets
 * locked in.
 */
interface ProductSnapshotProvider
{
    /**
     * @return array{code: string, description: string, type: string, unit_code: string}|null null when no such product exists
     */
    public function snapshot(CompanyId $companyId, string $productId): ?array;
}
