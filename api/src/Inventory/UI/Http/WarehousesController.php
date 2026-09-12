<?php

declare(strict_types=1);

namespace App\Inventory\UI\Http;

use App\Inventory\Application\Command\CreateWarehouse;
use App\Inventory\Application\Command\DeactivateWarehouse;
use App\Inventory\Application\Command\UpdateWarehouse;
use App\Inventory\Application\Query\GetWarehouse;
use App\Inventory\Application\Query\ListWarehouses;
use App\Inventory\Application\Query\WarehouseView;
use App\Inventory\Domain\WarehouseId;
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
 * `CompanyRouteListener`; the `stock.manage`/`stock.read` permission
 * checks happen in each handler (docs/plans/phase-1.md task 1.9). Plain
 * list, no cursor pagination — same reasoning as `PriceListsController`
 * (task 1.7): nothing in this task's bullets calls for one.
 */
#[OA\Tag(name: 'Warehouses')]
final class WarehousesController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/warehouses', name: 'warehouses_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Warehouse created.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the stock.manage permission.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation.')]
    public function create(#[MapRequestPayload] WarehouseRequest $request, Request $httpRequest): Response
    {
        $warehouseId = WarehouseId::generate();

        $this->commandBus->dispatch(new CreateWarehouse(
            $warehouseId,
            $this->currentActorId->id(),
            $request->code,
            $request->name,
            $request->address,
            $request->is_default,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $warehouseId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/warehouses', name: 'warehouses_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Every warehouse for this company.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'address', type: 'string', nullable: true),
            new OA\Property(property: 'is_default', type: 'boolean'),
            new OA\Property(property: 'active', type: 'boolean'),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<WarehouseView> $warehouses */
        $warehouses = $this->handle(new ListWarehouses());

        return new JsonResponse(['items' => array_map(self::toArray(...), $warehouses)]);
    }

    #[Route('/api/v1/companies/{companyId}/warehouses/{warehouseId}', name: 'warehouses_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The warehouse.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'is_default', type: 'boolean'),
        new OA\Property(property: 'active', type: 'boolean'),
    ]))]
    #[OA\Response(response: 404, description: 'No such warehouse.')]
    public function get(string $warehouseId): JsonResponse
    {
        /** @var WarehouseView $warehouse */
        $warehouse = $this->handle(new GetWarehouse($this->parseId($warehouseId)));

        return new JsonResponse(self::toArray($warehouse));
    }

    #[Route('/api/v1/companies/{companyId}/warehouses/{warehouseId}', name: 'warehouses_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Warehouse updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the stock.manage permission.')]
    #[OA\Response(response: 404, description: 'No such warehouse.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation.')]
    public function update(string $warehouseId, #[MapRequestPayload] WarehouseRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateWarehouse(
            $this->parseId($warehouseId),
            $this->currentActorId->id(),
            $request->code,
            $request->name,
            $request->address,
            $request->is_default,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/warehouses/{warehouseId}', name: 'warehouses_deactivate', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Warehouse deactivated (active = false; not a hard delete).')]
    #[OA\Response(response: 403, description: 'The caller lacks the stock.manage permission.')]
    #[OA\Response(response: 404, description: 'No such warehouse.')]
    public function deactivate(string $warehouseId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new DeactivateWarehouse(
            $this->parseId($warehouseId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    /**
     * @return array{id: string, code: string, name: string, address: ?string, is_default: bool, active: bool}
     */
    private static function toArray(WarehouseView $warehouse): array
    {
        return [
            'id' => $warehouse->id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'address' => $warehouse->address,
            'is_default' => $warehouse->isDefault,
            'active' => $warehouse->active,
        ];
    }

    private function parseId(string $warehouseId): WarehouseId
    {
        try {
            return WarehouseId::fromString($warehouseId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such warehouse.');
        }
    }
}
