<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Command\CreateConversionDraft;
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
 * technical-scope.md §6.8/docs/plans/phase-2.md task 2.8: prefills a new
 * draft of `document_type` from a working document's still-pending
 * quantities — the caller edits it (e.g. for a partial conversion) and
 * issues it through task 2.6's ordinary pipeline like any other draft.
 */
#[OA\Tag(name: 'Documents')]
final class ConvertDocumentController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/api/v1/companies/{companyId}/documents/{documentId}/convert', name: 'documents_convert', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'A new draft of the target type, prefilled from the source document\'s pending quantities.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'No such document.')]
    #[OA\Response(response: 422, description: 'The source is not a convertible working document, is cancelled, the target type is not an allowed conversion, or nothing is left to convert.')]
    public function convert(string $documentId, #[MapRequestPayload] ConvertDocumentRequest $request, Request $httpRequest): JsonResponse
    {
        /** @var DocumentDraftId $draftId */
        $draftId = $this->handle(new CreateConversionDraft(
            $this->parseId($documentId),
            $request->document_type,
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
