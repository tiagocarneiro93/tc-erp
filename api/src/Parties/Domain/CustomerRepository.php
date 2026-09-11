<?php

declare(strict_types=1);

namespace App\Parties\Domain;

use App\Shared\Domain\CompanyId;

interface CustomerRepository
{
    public function find(CompanyId $companyId, CustomerId $id): ?Customer;

    /**
     * Ordered ascending by id, for {@see \App\Shared\Domain\Http\CursorPaginator}
     * (same in-memory-pagination-over-a-DB-filtered-list shape as
     * `ListMyCompaniesHandler`). `$search` matches code, NIF or name.
     *
     * @return list<Customer>
     */
    public function search(CompanyId $companyId, ?string $search): array;

    public function save(Customer $customer): void;
}
