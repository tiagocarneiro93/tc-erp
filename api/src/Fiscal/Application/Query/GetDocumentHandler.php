<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\Exception\DocumentNotFound;
use App\Fiscal\Domain\IssuedDocumentReader;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetDocumentHandler
{
    public function __construct(
        private readonly IssuedDocumentReader $documents,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, pricing_mode: string, rounding_method: string, gross_total: string, lines: list<array<string, mixed>>}
     */
    public function __invoke(GetDocument $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.read', $companyId)) {
            throw new PermissionDenied();
        }

        $document = $this->documents->find($companyId, $query->documentId);

        if (null === $document) {
            throw new DocumentNotFound();
        }

        return $document;
    }
}
