<?php

declare(strict_types=1);

namespace App\Company\UI\Http;

use App\Company\Application\Command\TestAtCredentials;
use App\Company\Application\Command\TestAtCredentialsResult;
use App\Company\Application\Command\UpdateAtCredentials;
use App\Shared\Domain\Security\CurrentActorId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * `{companyId}` is resolved and authorized (membership) by
 * `CompanyRouteListener`; the `company.manage` permission check happens in
 * each command handler. The "test" endpoint only round-trips this app's own
 * encrypt/decrypt and checks the subuser format — it never calls the AT
 * webservice (that's Phase 3, docs/plans/phase-1.md task 1.4).
 */
#[OA\Tag(name: 'Company')]
final class AtCredentialsController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly CurrentActorId $currentActorId,
    ) {
    }

    #[Route('/api/v1/companies/{companyId}/at-credentials', name: 'at_credentials_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Credentials stored (encrypted at rest).')]
    #[OA\Response(response: 403, description: 'The caller lacks the company.manage permission.')]
    #[OA\Response(response: 404, description: 'The caller is not a member of this company.')]
    #[OA\Response(response: 422, description: 'Invalid subuser format, or the request payload failed validation.')]
    public function update(#[MapRequestPayload] UpdateAtCredentialsRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateAtCredentials(
            $this->currentActorId->id(),
            $request->subuser,
            $request->password,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/at-credentials/test', name: 'at_credentials_test', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Stub validation result (format/round-trip only, not a real AT check).', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'valid', type: 'boolean'),
        new OA\Property(property: 'checked_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the company.manage permission.')]
    #[OA\Response(response: 404, description: 'The caller is not a member of this company, or no credentials are configured yet.')]
    public function test(): JsonResponse
    {
        $envelope = $this->commandBus->dispatch(new TestAtCredentials());
        $stamp = $envelope->last(HandledStamp::class);
        $result = $stamp?->getResult();

        if (!$result instanceof TestAtCredentialsResult) {
            throw new \LogicException('TestAtCredentialsHandler did not return a TestAtCredentialsResult.');
        }

        return new JsonResponse([
            'valid' => $result->valid,
            'checked_at' => $result->checkedAt,
            'error' => $result->error,
        ]);
    }
}
