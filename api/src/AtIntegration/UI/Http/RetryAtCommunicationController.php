<?php

declare(strict_types=1);

namespace App\AtIntegration\UI\Http;

use App\AtIntegration\Application\Command\RetryAtCommunication;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Security\CurrentActorId;
use App\Shared\Infrastructure\Http\IdempotencyKeyGuard;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * technical-scope.md §7.5 / docs/plans/phase-3.md task 3.2: the manual retry
 * for a document whose AT communication `failed` or was `rejected`.
 * `Idempotency-Key` required (§9.1: communicating endpoints).
 */
#[OA\Tag(name: 'Documents')]
final class RetryAtCommunicationController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly IdempotencyKeyGuard $idempotencyKeyGuard,
        private readonly CompanyContext $companyContext,
        private readonly CurrentActorId $currentActorId,
    ) {
    }

    #[Route('/api/v1/companies/{companyId}/documents/{documentId}/at-communication/retry', name: 'documents_at_communication_retry', methods: ['POST'])]
    #[OA\Response(response: 202, description: 'Queued for another attempt.')]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'No such document.')]
    #[OA\Response(response: 422, description: 'The document has no failed or rejected AT communication to retry.')]
    public function retry(string $documentId, Request $httpRequest): Response
    {
        if (!Uuid::isValid($documentId)) {
            throw new NotFoundHttpException('No such document.');
        }

        return $this->idempotencyKeyGuard->guard($httpRequest, $this->companyContext->companyId(), function () use ($documentId, $httpRequest): Response {
            $this->commandBus->dispatch(new RetryAtCommunication(
                $documentId,
                $this->currentActorId->id(),
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));

            return new Response(null, 202);
        });
    }
}
