<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Command\CreateCreditNoteDraft;
use App\Fiscal\Domain\DocumentDraftId;
use App\Fiscal\Domain\DocumentId;
use App\Shared\Domain\Security\CurrentActorId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §7.4/docs/plans/phase-2.md task 2.7: prefills a new
 * draft from an existing document's lines — the caller edits it (e.g. for
 * a partial credit note) and issues it through task 2.6's ordinary
 * pipeline like any other draft.
 */
#[OA\Tag(name: 'Documents')]
final class CreditNoteController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/api/v1/companies/{companyId}/documents/{documentId}/credit-note', name: 'documents_credit_note', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'A new NC draft, prefilled from the original document.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'No such document.')]
    #[OA\Response(response: 422, description: 'The document is cancelled or already fully rectified (Despacho 8632/2014 §3.3.7).')]
    public function creditNote(string $documentId, #[MapRequestPayload] CreditNoteRequest $request, Request $httpRequest): JsonResponse
    {
        /** @var DocumentDraftId $draftId */
        $draftId = $this->handle(new CreateCreditNoteDraft(
            $this->parseId($documentId),
            $request->reason,
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $draftId->toString()], 201);
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
