<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;

interface DocumentDraftRepository
{
    public function find(CompanyId $companyId, DocumentDraftId $id): ?DocumentDraft;

    /**
     * Ordered descending by last update (most recently edited first).
     *
     * @return list<DocumentDraft>
     */
    public function findAll(CompanyId $companyId): array;

    public function save(DocumentDraft $draft): void;

    public function remove(DocumentDraft $draft): void;
}
