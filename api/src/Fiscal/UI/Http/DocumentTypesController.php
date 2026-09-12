<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Query\ListDocumentTypes;
use App\Fiscal\Domain\DocumentType;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Global, read-only reference data — no company scope, any authenticated
 * user may read it. Inspection/debug value only: Fiscal's own use cases
 * read this table directly in later phases, no dependency on this
 * endpoint (docs/plans/phase-1.md task 1.3).
 */
#[OA\Tag(name: 'Reference data')]
final class DocumentTypesController
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/document-types', name: 'document_types_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'All fiscal document types.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'saft_section', type: 'string'),
            new OA\Property(property: 'signed', type: 'boolean'),
            new OA\Property(property: 'stock_effect', type: 'string'),
            new OA\Property(property: 'account_effect', type: 'string'),
            new OA\Property(property: 'requires_at_prior_communication', type: 'boolean'),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<DocumentType> $types */
        $types = $this->handle(new ListDocumentTypes());

        return new JsonResponse(['items' => array_map(static fn (DocumentType $t) => [
            'code' => $t->code(),
            'name' => $t->name(),
            'saft_section' => $t->saftSection(),
            'signed' => $t->isSigned(),
            'stock_effect' => $t->stockEffect(),
            'account_effect' => $t->accountEffect(),
            'requires_at_prior_communication' => $t->requiresAtPriorCommunication(),
        ], $types)]);
    }
}
