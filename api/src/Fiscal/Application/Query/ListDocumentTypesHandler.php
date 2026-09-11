<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentType;
use App\Fiscal\Domain\DocumentTypeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListDocumentTypesHandler
{
    public function __construct(
        private readonly DocumentTypeRepository $documentTypes,
    ) {
    }

    /**
     * @return list<DocumentType>
     */
    public function __invoke(ListDocumentTypes $query): array
    {
        return $this->documentTypes->findAll();
    }
}
