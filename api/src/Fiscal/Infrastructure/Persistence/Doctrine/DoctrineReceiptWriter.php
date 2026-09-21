<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\ReceiptWriter;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Raw DBAL, matching {@see DoctrineDocumentWriter} — neither `receipts`
 * nor `receipt_allocations` has a Doctrine entity.
 */
final class DoctrineReceiptWriter implements ReceiptWriter
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function insert(CompanyId $companyId, array $receipt, array $allocations): void
    {
        $company = ['company_id' => $companyId->toString()];

        $this->connection->insert('receipts', $company + $receipt);

        foreach ($allocations as $allocation) {
            $this->connection->insert('receipt_allocations', $company + $allocation);
        }
    }
}
