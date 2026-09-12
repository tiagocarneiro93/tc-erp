<?php

declare(strict_types=1);

namespace App\Parties\UI\Http;

use App\Parties\Application\Command\CreateSupplier;
use App\Parties\Application\Command\DeactivateSupplier;
use App\Parties\Application\Command\UpdateSupplier;
use App\Parties\Application\Query\GetSupplier;
use App\Parties\Application\Query\ListSuppliers;
use App\Parties\Application\Query\SupplierView;
use App\Parties\Domain\SupplierId;
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
 * permission checks (ADR 0002 groups suppliers under the same pair as
 * customers) happen in each handler (docs/plans/phase-1.md task 1.5).
 */
#[OA\Tag(name: 'Suppliers')]
final class SuppliersController
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

    #[Route('/api/v1/companies/{companyId}/suppliers', name: 'suppliers_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Supplier created.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the customers.manage permission.')]
    #[OA\Response(response: 422, description: 'Invalid NIF, country, or the request payload failed validation.')]
    public function create(#[MapRequestPayload] SupplierRequest $request, Request $httpRequest): Response
    {
        $supplierId = SupplierId::generate();

        $this->commandBus->dispatch(new CreateSupplier(
            $supplierId,
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

        return new JsonResponse(['id' => $supplierId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/suppliers', name: 'suppliers_list', methods: ['GET'])]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'cursor', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Suppliers matching the search, if any.', content: new OA\JsonContent(properties: [
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
            new OA\Property(property: 'active', type: 'boolean'),
        ], type: 'object')),
        new OA\Property(property: 'next_cursor', type: 'string', nullable: true),
    ]))]
    public function list(Request $request): JsonResponse
    {
        /** @var list<SupplierView> $suppliers */
        $suppliers = $this->handle(new ListSuppliers($request->query->get('search')));

        $page = $this->paginator->paginate(
            $suppliers,
            $request->query->get('cursor'),
            $request->query->getInt('limit') ?: null,
            static fn (SupplierView $s): string => $s->id,
        );

        return new JsonResponse([
            'items' => array_map(self::toArray(...), $page->items),
            'next_cursor' => $page->nextCursor,
        ]);
    }

    #[Route('/api/v1/companies/{companyId}/suppliers/{supplierId}', name: 'suppliers_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The supplier.', content: new OA\JsonContent(properties: [
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
        new OA\Property(property: 'active', type: 'boolean'),
    ]))]
    #[OA\Response(response: 404, description: 'No such supplier.')]
    public function get(string $supplierId): JsonResponse
    {
        /** @var SupplierView $supplier */
        $supplier = $this->handle(new GetSupplier($this->parseId($supplierId)));

        return new JsonResponse(self::toArray($supplier));
    }

    #[Route('/api/v1/companies/{companyId}/suppliers/{supplierId}', name: 'suppliers_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Supplier updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the customers.manage permission.')]
    #[OA\Response(response: 404, description: 'No such supplier.')]
    #[OA\Response(response: 422, description: 'Invalid NIF, country, or the request payload failed validation.')]
    public function update(string $supplierId, #[MapRequestPayload] SupplierRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateSupplier(
            $this->parseId($supplierId),
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

    #[Route('/api/v1/companies/{companyId}/suppliers/{supplierId}', name: 'suppliers_deactivate', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Supplier deactivated (active = false; not a hard delete).')]
    #[OA\Response(response: 403, description: 'The caller lacks the customers.manage permission.')]
    #[OA\Response(response: 404, description: 'No such supplier.')]
    public function deactivate(string $supplierId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new DeactivateSupplier(
            $this->parseId($supplierId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    /**
     * @return array{id: string, code: string, nif: string, name: string, address: ?string, postal_code: ?string, city: ?string, country: string, email: ?string, phone: ?string, payment_terms_id: ?string, active: bool}
     */
    private static function toArray(SupplierView $supplier): array
    {
        return [
            'id' => $supplier->id,
            'code' => $supplier->code,
            'nif' => $supplier->nif,
            'name' => $supplier->name,
            'address' => $supplier->address,
            'postal_code' => $supplier->postalCode,
            'city' => $supplier->city,
            'country' => $supplier->country,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'payment_terms_id' => $supplier->paymentTermsId,
            'active' => $supplier->active,
        ];
    }

    private function parseId(string $supplierId): SupplierId
    {
        try {
            return SupplierId::fromString($supplierId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such supplier.');
        }
    }
}
