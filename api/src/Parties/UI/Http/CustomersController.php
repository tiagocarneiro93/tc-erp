<?php

declare(strict_types=1);

namespace App\Parties\UI\Http;

use App\Parties\Application\Command\CreateCustomer;
use App\Parties\Application\Command\DeactivateCustomer;
use App\Parties\Application\Command\UpdateCustomer;
use App\Parties\Application\Query\CustomerView;
use App\Parties\Application\Query\GetCustomer;
use App\Parties\Application\Query\ListCustomers;
use App\Parties\Domain\CustomerId;
use App\Shared\Domain\Http\CursorPaginator;
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
 * `CompanyRouteListener`; the `customers.manage`/`customers.read`
 * permission checks happen in each handler (docs/plans/phase-1.md task
 * 1.5).
 */
#[OA\Tag(name: 'Customers')]
final class CustomersController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
        private readonly CursorPaginator $paginator,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/customers', name: 'customers_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Customer created.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the customers.manage permission.')]
    #[OA\Response(response: 422, description: 'Invalid NIF, country, or the request payload failed validation.')]
    public function create(#[MapRequestPayload] CustomerRequest $request, Request $httpRequest): Response
    {
        $customerId = CustomerId::generate();

        $this->commandBus->dispatch(new CreateCustomer(
            $customerId,
            $this->currentActorId->id(),
            $request->code,
            $request->nif,
            $request->name,
            $request->address,
            $request->postal_code,
            $request->city,
            $request->country,
            $request->email,
            $request->phone,
            $request->payment_terms_id,
            $request->is_final_consumer,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $customerId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/customers', name: 'customers_list', methods: ['GET'])]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'cursor', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Customers matching the search, if any.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'nif', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'address', type: 'string', nullable: true),
            new OA\Property(property: 'postal_code', type: 'string', nullable: true),
            new OA\Property(property: 'city', type: 'string', nullable: true),
            new OA\Property(property: 'country', type: 'string'),
            new OA\Property(property: 'email', type: 'string', nullable: true),
            new OA\Property(property: 'phone', type: 'string', nullable: true),
            new OA\Property(property: 'payment_terms_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'is_final_consumer', type: 'boolean'),
            new OA\Property(property: 'active', type: 'boolean'),
        ], type: 'object')),
        new OA\Property(property: 'next_cursor', type: 'string', nullable: true),
    ]))]
    public function list(Request $request): JsonResponse
    {
        /** @var list<CustomerView> $customers */
        $customers = $this->handle(new ListCustomers($request->query->get('search')));

        $page = $this->paginator->paginate(
            $customers,
            $request->query->get('cursor'),
            $request->query->getInt('limit') ?: null,
            static fn (CustomerView $c): string => $c->id,
        );

        return new JsonResponse([
            'items' => array_map(self::toArray(...), $page->items),
            'next_cursor' => $page->nextCursor,
        ]);
    }

    #[Route('/api/v1/companies/{companyId}/customers/{customerId}', name: 'customers_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The customer.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'nif', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'postal_code', type: 'string', nullable: true),
        new OA\Property(property: 'city', type: 'string', nullable: true),
        new OA\Property(property: 'country', type: 'string'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'payment_terms_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'is_final_consumer', type: 'boolean'),
        new OA\Property(property: 'active', type: 'boolean'),
    ]))]
    #[OA\Response(response: 404, description: 'No such customer.')]
    public function get(string $customerId): JsonResponse
    {
        /** @var CustomerView $customer */
        $customer = $this->handle(new GetCustomer($this->parseId($customerId)));

        return new JsonResponse(self::toArray($customer));
    }

    #[Route('/api/v1/companies/{companyId}/customers/{customerId}', name: 'customers_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Customer updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the customers.manage permission.')]
    #[OA\Response(response: 404, description: 'No such customer.')]
    #[OA\Response(response: 422, description: 'Invalid NIF, country, or the request payload failed validation.')]
    public function update(string $customerId, #[MapRequestPayload] CustomerRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateCustomer(
            $this->parseId($customerId),
            $this->currentActorId->id(),
            $request->code,
            $request->nif,
            $request->name,
            $request->address,
            $request->postal_code,
            $request->city,
            $request->country,
            $request->email,
            $request->phone,
            $request->payment_terms_id,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/customers/{customerId}', name: 'customers_deactivate', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Customer deactivated (active = false; not a hard delete).')]
    #[OA\Response(response: 403, description: 'The caller lacks the customers.manage permission.')]
    #[OA\Response(response: 404, description: 'No such customer.')]
    public function deactivate(string $customerId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new DeactivateCustomer(
            $this->parseId($customerId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    /**
     * @return array{id: string, code: string, nif: string, name: string, address: ?string, postal_code: ?string, city: ?string, country: string, email: ?string, phone: ?string, payment_terms_id: ?string, is_final_consumer: bool, active: bool}
     */
    private static function toArray(CustomerView $customer): array
    {
        return [
            'id' => $customer->id,
            'code' => $customer->code,
            'nif' => $customer->nif,
            'name' => $customer->name,
            'address' => $customer->address,
            'postal_code' => $customer->postalCode,
            'city' => $customer->city,
            'country' => $customer->country,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'payment_terms_id' => $customer->paymentTermsId,
            'is_final_consumer' => $customer->isFinalConsumer,
            'active' => $customer->active,
        ];
    }

    private function parseId(string $customerId): CustomerId
    {
        try {
            return CustomerId::fromString($customerId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such customer.');
        }
    }
}
