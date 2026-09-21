<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Command\IssueDraft;
use App\Fiscal\Domain\DocumentDraftId;
use App\Fiscal\Domain\DocumentId;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Security\CurrentActorId;
use App\Shared\Infrastructure\Http\IdempotencyKeyGuard;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §7.1: the only route that turns a draft into an
 * issued, immutable document. `Idempotency-Key` is required (§9.1) and
 * handled entirely by {@see IdempotencyKeyGuard} wrapping this action — a
 * retried request with the same key and body returns the exact original
 * response, never a second document.
 */
#[OA\Tag(name: 'Documents')]
final class IssueDraftController
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

    #[Route('/api/v1/companies/{companyId}/documents/drafts/{draftId}/issue', name: 'documents_issue', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Issued.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'No such draft, or its series does not exist.')]
    #[OA\Response(response: 422, description: 'The draft fails validation, has no series, its series cannot issue, or it could not be calculated.')]
    public function issue(string $draftId, Request $httpRequest): Response
    {
        $parsedDraftId = $this->parseId($draftId);

        return $this->idempotencyKeyGuard->guard($httpRequest, $this->companyContext->companyId(), function () use ($parsedDraftId, $httpRequest): Response {
            $idempotencyKey = $httpRequest->headers->get('Idempotency-Key', '');

            /** @var DocumentId $documentId */
            $documentId = $this->handle(new IssueDraft(
                $parsedDraftId,
                $idempotencyKey,
                $this->currentActorId->id(),
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));

            return new JsonResponse(['id' => $documentId->toString()], 201);
        });
    }

    private function parseId(string $draftId): DocumentDraftId
    {
        try {
            return DocumentDraftId::fromString($draftId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such draft.');
        }
    }
}
