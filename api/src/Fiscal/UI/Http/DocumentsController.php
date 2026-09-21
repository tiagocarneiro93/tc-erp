<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Query\GetDocument;
use App\Fiscal\Application\Query\ListDocuments;
use App\Fiscal\Domain\DocumentId;
use App\Shared\Domain\Http\CursorPaginator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §9/docs/plans/phase-2.md task 2.11: read-only access
 * to already-issued documents — the list and detail screens. Everything
 * that mutates a document (issue, cancel, credit-note, convert) already
 * has its own controller (tasks 2.6/2.7/2.8/2.10); this one only reads.
 */
#[OA\Tag(name: 'Documents')]
final class DocumentsController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly CursorPaginator $paginator,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/documents', name: 'documents_list', methods: ['GET'])]
    #[OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'customer', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'cursor', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Documents matching the filters, if any.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'document_type', type: 'string'),
            new OA\Property(property: 'document_no', type: 'string'),
            new OA\Property(property: 'status', type: 'string'),
            new OA\Property(property: 'customer_id', type: 'string', nullable: true),
            new OA\Property(property: 'customer_name', type: 'string'),
            new OA\Property(property: 'issue_date', type: 'string', format: 'date-time'),
            new OA\Property(property: 'gross_total', type: 'string'),
            new OA\Property(property: 'open_amount', type: 'string'),
        ], type: 'object')),
        new OA\Property(property: 'next_cursor', type: 'string', nullable: true),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.read permission.')]
    public function list(Request $request): JsonResponse
    {
        /** @var list<array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, customer_name: string, issue_date: string, gross_total: string, open_amount: string}> $documents */
        $documents = $this->handle(new ListDocuments(
            $request->query->get('type'),
            $request->query->get('status'),
            $request->query->get('customer'),
            $request->query->get('from'),
            $request->query->get('to'),
        ));

        $page = $this->paginator->paginate(
            $documents,
            $request->query->get('cursor'),
            $request->query->getInt('limit', 0) ?: null,
            static fn (array $document): string => $document['id'],
        );

        return new JsonResponse(['items' => $page->items, 'next_cursor' => $page->nextCursor]);
    }

    #[Route('/api/v1/companies/{companyId}/documents/{documentId}', name: 'documents_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The document, with its lines.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'document_type', type: 'string'),
        new OA\Property(property: 'document_no', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'customer_id', type: 'string', nullable: true),
        new OA\Property(property: 'pricing_mode', type: 'string'),
        new OA\Property(property: 'rounding_method', type: 'string'),
        new OA\Property(property: 'gross_total', type: 'string'),
        new OA\Property(property: 'can_cancel', type: 'boolean', description: 'Whether POST .../cancel would currently succeed for this document.'),
        new OA\Property(property: 'can_credit_note', type: 'boolean', description: 'Whether POST .../credit-note would currently succeed for this document.'),
        new OA\Property(property: 'convert_targets', type: 'array', items: new OA\Items(type: 'string'), description: 'Document types POST .../convert would currently accept for this document; empty if it cannot be converted at all.'),
        new OA\Property(property: 'lines', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'line_number', type: 'integer'),
            new OA\Property(property: 'pending_quantity', type: 'string'),
            new OA\Property(property: 'product_id', type: 'string', nullable: true),
            new OA\Property(property: 'product_code', type: 'string'),
            new OA\Property(property: 'product_description', type: 'string'),
            new OA\Property(property: 'product_type', type: 'string'),
            new OA\Property(property: 'unit_code', type: 'string'),
            new OA\Property(property: 'quantity', type: 'string'),
            new OA\Property(property: 'unit_price', type: 'string'),
            new OA\Property(property: 'discount_percent', type: 'string', nullable: true),
            new OA\Property(property: 'tax_region', type: 'string'),
            new OA\Property(property: 'tax_code', type: 'string'),
            new OA\Property(property: 'exemption_reason_code', type: 'string', nullable: true),
        ], type: 'object')),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.read permission.')]
    #[OA\Response(response: 404, description: 'No such document.')]
    public function get(string $documentId): JsonResponse
    {
        $document = $this->handle(new GetDocument($this->parseId($documentId)));

        return new JsonResponse($document);
    }

    private function parseId(string $documentId): DocumentId
    {
        try {
            return DocumentId::fromString($documentId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such document.');
        }
    }
}
