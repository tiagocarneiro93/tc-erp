<?php

declare(strict_types=1);

namespace App\Output\UI\Http;

use App\Output\Application\EmailDocument;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Security\CurrentActorId;
use App\Shared\Infrastructure\Http\IdempotencyKeyGuard;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §7.8/§9: e-mails an issued document, sealed, to the given
 * recipients (default: the customer's own address). Asynchronous — 202 means
 * queued. `Idempotency-Key` required (§9.1: it communicates outwards).
 */
#[OA\Tag(name: 'Documents')]
final class EmailDocumentController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly IdempotencyKeyGuard $idempotencyKeyGuard,
        private readonly CompanyContext $companyContext,
        private readonly CurrentActorId $currentActorId,
    ) {
    }

    #[Route('/api/v1/companies/{companyId}/documents/{documentId}/send', name: 'documents_send', methods: ['POST'])]
    #[OA\Response(response: 202, description: 'Queued: the sealed PDF is made (once) and mailed in the background.')]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'No such issued document (a draft is not a document).')]
    #[OA\Response(response: 422, description: 'No recipient given and the customer has no e-mail address, or an address is invalid.')]
    public function send(string $documentId, #[MapRequestPayload] EmailDocumentRequest $request, Request $httpRequest): Response
    {
        return $this->idempotencyKeyGuard->guard($httpRequest, $this->companyContext->companyId(), function () use ($documentId, $request, $httpRequest): Response {
            $this->commandBus->dispatch(new EmailDocument(
                $documentId,
                $request->recipients,
                $request->message,
                $this->currentActorId->id(),
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));

            return new Response(null, 202);
        });
    }
}
