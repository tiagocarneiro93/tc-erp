<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Query\GetReceipt;
use App\Fiscal\Application\Query\ListReceipts;
use App\Fiscal\Domain\ReceiptId;
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
 * to already-issued receipts — issuing one is {@see IssueReceiptController}
 * (task 2.9).
 */
#[OA\Tag(name: 'Receipts')]
final class ReceiptsController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly CursorPaginator $paginator,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/receipts', name: 'receipts_list', methods: ['GET'])]
    #[OA\Parameter(name: 'cursor', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Every issued receipt.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'document_no', type: 'string'),
            new OA\Property(property: 'atcud', type: 'string'),
            new OA\Property(property: 'issue_date', type: 'string', format: 'date-time'),
            new OA\Property(property: 'customer_name', type: 'string'),
            new OA\Property(property: 'total', type: 'string'),
            new OA\Property(property: 'payment_method', type: 'string'),
            new OA\Property(property: 'status', type: 'string'),
        ], type: 'object')),
        new OA\Property(property: 'next_cursor', type: 'string', nullable: true),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.read permission.')]
    public function list(Request $request): JsonResponse
    {
        /** @var list<array{id: string, document_no: string, atcud: string, issue_date: string, customer_name: string, total: string, payment_method: string, status: string}> $receipts */
        $receipts = $this->handle(new ListReceipts());

        $page = $this->paginator->paginate(
            $receipts,
            $request->query->get('cursor'),
            $request->query->getInt('limit', 0) ?: null,
            static fn (array $receipt): string => $receipt['id'],
        );

        return new JsonResponse(['items' => $page->items, 'next_cursor' => $page->nextCursor]);
    }

    #[Route('/api/v1/companies/{companyId}/receipts/{receiptId}', name: 'receipts_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The receipt, with its allocations.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'document_no', type: 'string'),
        new OA\Property(property: 'atcud', type: 'string'),
        new OA\Property(property: 'issue_date', type: 'string', format: 'date-time'),
        new OA\Property(property: 'customer_id', type: 'string', nullable: true),
        new OA\Property(property: 'customer_name', type: 'string'),
        new OA\Property(property: 'total', type: 'string'),
        new OA\Property(property: 'payment_method', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'allocations', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'document_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'document_no', type: 'string'),
            new OA\Property(property: 'amount', type: 'string'),
            new OA\Property(property: 'settlement_amount', type: 'string'),
        ], type: 'object')),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.read permission.')]
    #[OA\Response(response: 404, description: 'No such receipt.')]
    public function get(string $receiptId): JsonResponse
    {
        $receipt = $this->handle(new GetReceipt($this->parseId($receiptId)));

        return new JsonResponse($receipt);
    }

    private function parseId(string $receiptId): ReceiptId
    {
        try {
            return ReceiptId::fromString($receiptId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such receipt.');
        }
    }
}
