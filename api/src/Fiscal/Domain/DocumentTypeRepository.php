<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

interface DocumentTypeRepository
{
    /**
     * @return list<DocumentType>
     */
    public function findAll(): array;

    public function find(string $code): ?DocumentType;
}
