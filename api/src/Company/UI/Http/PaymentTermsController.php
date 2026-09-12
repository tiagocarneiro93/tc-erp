<?php

declare(strict_types=1);

namespace App\Company\UI\Http;

use App\Company\Application\Command\CreatePaymentTerms;
use App\Company\Application\Command\DeactivatePaymentTerms;
use App\Company\Application\Command\UpdatePaymentTerms;
use App\Company\Application\Query\ListPaymentTerms;
use App\Company\Application\Query\PaymentTermsView;
use App\Company\Domain\PaymentTermsId;
use App\Shared\Domain\Security\CurrentActorId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * `{companyId}` is resolved and authorized (membership) by
 * `CompanyRouteListener`; write endpoints require `company.manage`, `list`
 * requires no permission (any member may read, matching
 * `CompanyProfileController`'s own GET).
 */
#[OA\Tag(name: 'Company')]
final class PaymentTermsController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/payment-terms', name: 'payment_terms_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Payment terms created.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the company.manage permission.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation.')]
    public function create(#[MapRequestPayload] PaymentTermsRequest $request, Request $httpRequest): Response
    {
        $paymentTermsId = PaymentTermsId::generate();

        $this->commandBus->dispatch(new CreatePaymentTerms(
            $paymentTermsId,
            $this->currentActorId->id(),
            $request->name,
            $request->days,
            $request->is_default,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $paymentTermsId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/payment-terms', name: 'payment_terms_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Every payment term for this company.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'days', type: 'integer'),
            new OA\Property(property: 'is_default', type: 'boolean'),
            new OA\Property(property: 'active', type: 'boolean'),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<PaymentTermsView> $items */
        $items = $this->handle(new ListPaymentTerms());

        return new JsonResponse(['items' => array_map(self::toArray(...), $items)]);
    }

    #[Route('/api/v1/companies/{companyId}/payment-terms/{paymentTermsId}', name: 'payment_terms_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Payment terms updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the company.manage permission.')]
    #[OA\Response(response: 404, description: 'No such payment terms.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation.')]
    public function update(string $paymentTermsId, #[MapRequestPayload] PaymentTermsRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdatePaymentTerms(
            $this->parseId($paymentTermsId),
            $this->currentActorId->id(),
            $request->name,
            $request->days,
            $request->is_default,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/payment-terms/{paymentTermsId}', name: 'payment_terms_deactivate', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Payment terms deactivated (active = false; not a hard delete).')]
    #[OA\Response(response: 403, description: 'The caller lacks the company.manage permission.')]
    #[OA\Response(response: 404, description: 'No such payment terms.')]
    public function deactivate(string $paymentTermsId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new DeactivatePaymentTerms(
            $this->parseId($paymentTermsId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    /**
     * @return array{id: string, name: string, days: int, is_default: bool, active: bool}
     */
    private static function toArray(PaymentTermsView $paymentTerms): array
    {
        return [
            'id' => $paymentTerms->id,
            'name' => $paymentTerms->name,
            'days' => $paymentTerms->days,
            'is_default' => $paymentTerms->isDefault,
            'active' => $paymentTerms->active,
        ];
    }

    private function parseId(string $paymentTermsId): PaymentTermsId
    {
        try {
            return PaymentTermsId::fromString($paymentTermsId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such payment terms.');
        }
    }
}
