<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;

interface SeriesRepository
{
    public function find(CompanyId $companyId, SeriesId $id): ?Series;

    /**
     * Used to reject a duplicate before it hits the `UNIQUE(company_id,
     * document_type, code)` constraint, so the API can return a stable
     * 422 instead of a raw database error.
     */
    public function findByCode(CompanyId $companyId, string $documentType, string $code): ?Series;

    /**
     * Ordered ascending by document type, then code.
     *
     * @return list<Series>
     */
    public function findAll(CompanyId $companyId): array;

    public function save(Series $series): void;
}
