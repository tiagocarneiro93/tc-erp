<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\CustomerHasIssuedDocuments;
use App\Shared\Domain\Fiscal\ProductHasIssuedDocuments;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Raw DBAL, not the ORM: `documents`/`document_lines` (docs/plans/phase-2.md
 * task 2.3) have no Doctrine entity yet — nothing in Fiscal's own
 * application code reads or writes them until tasks 2.4/2.6 exist. This
 * class exists only to answer Parties'/Catalog's yes/no immutability
 * question in the meantime.
 */
final class IssuedDocumentsChecker implements CustomerHasIssuedDocuments, ProductHasIssuedDocuments
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function forCustomer(CompanyId $companyId, string $customerId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM documents WHERE company_id = :companyId AND customer_id = :customerId LIMIT 1',
            ['companyId' => $companyId->toString(), 'customerId' => $customerId],
        );
    }

    public function forProduct(CompanyId $companyId, string $productId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM document_lines WHERE company_id = :companyId AND product_id = :productId LIMIT 1',
            ['companyId' => $companyId->toString(), 'productId' => $productId],
        );
    }
}
