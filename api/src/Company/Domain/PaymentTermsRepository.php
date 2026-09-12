<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Shared\Domain\CompanyId;

interface PaymentTermsRepository
{
    public function find(CompanyId $companyId, PaymentTermsId $id): ?PaymentTerms;

    public function findDefault(CompanyId $companyId): ?PaymentTerms;

    /**
     * Ordered ascending by days.
     *
     * @return list<PaymentTerms>
     */
    public function findAll(CompanyId $companyId): array;

    public function save(PaymentTerms $paymentTerms): void;
}
