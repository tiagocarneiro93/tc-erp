<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Command\CancelDocument;
use App\Fiscal\Domain\DocumentId;
use App\Shared\Domain\Security\CurrentActorId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §7.4/docs/plans/phase-2.md task 2.10.
 */
#[OA\Tag(name: 'Documents')]
final class CancelDocumentController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly CurrentActorId $currentActorId,
    ) {
    }

    #[Route('/api/v1/companies/{companyId}/documents/{documentId}/cancel', name: 'documents_cancel', methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Cancelled.')]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.cancel permission.')]
    #[OA\Response(response: 404, description: 'No such document.')]
    #[OA\Response(response: 422, description: 'The document is not active (already cancelled or, for a working document, already fully converted), already has an active credit note, is already settled by an active receipt, or may already have reached the AT (CIVA Art. 29.º §7).')]
    public function cancel(string $documentId, #[MapRequestPayload] CancelDocumentRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new CancelDocument(
            $this->parseId($documentId),
            $request->reason,
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new Response(null, 204);
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
