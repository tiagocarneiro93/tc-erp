<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Command\IssueReceipt;
use App\Fiscal\Domain\ReceiptId;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Security\CurrentActorId;
use App\Shared\Infrastructure\Http\IdempotencyKeyGuard;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §9/docs/plans/phase-2.md task 2.9: receipts issue
 * directly, in one call — unlike documents there is no separate draft to
 * create and edit first (§6.6's `receipts` table has no draft
 * counterpart). `Idempotency-Key` is required, same as
 * {@see IssueDraftController}.
 */
#[OA\Tag(name: 'Receipts')]
final class IssueReceiptController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly IdempotencyKeyGuard $idempotencyKeyGuard,
        private readonly CompanyContext $companyContext,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/api/v1/companies/{companyId}/receipts', name: 'receipts_issue', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Issued.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 400, description: 'An allocation is missing "document_id" or "amount".')]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'The series, or an allocation\'s document, does not exist.')]
    #[OA\Response(response: 422, description: 'No allocations, the series cannot issue, or an allocation is invalid (cancelled document, no receivable, or exceeds the open amount).')]
    public function issue(#[MapRequestPayload] IssueReceiptRequest $request, Request $httpRequest): Response
    {
        return $this->idempotencyKeyGuard->guard($httpRequest, $this->companyContext->companyId(), function () use ($request, $httpRequest): Response {
            /** @var ReceiptId $receiptId */
            $receiptId = $this->handle(new IssueReceipt(
                $request->series_id,
                $request->customer_id,
                $request->payment_method,
                array_map($this->toAllocation(...), $request->allocations),
                $httpRequest->headers->get('Idempotency-Key', ''),
                $this->currentActorId->id(),
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));

            return new JsonResponse(['id' => $receiptId->toString()], 201);
        });
    }

    /**
     * @param array{document_id?: string, amount?: string} $allocation
     *
     * @return array{document_id: string, amount: string}
     */
    private function toAllocation(array $allocation): array
    {
        if (!\is_string($allocation['document_id'] ?? null) || !\is_string($allocation['amount'] ?? null)) {
            throw new BadRequestHttpException('Each allocation needs a "document_id" and an "amount".');
        }

        return ['document_id' => $allocation['document_id'], 'amount' => $allocation['amount']];
    }
}
