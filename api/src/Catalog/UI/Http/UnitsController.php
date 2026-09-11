<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use App\Catalog\Application\Query\ListUnits;
use App\Catalog\Domain\Unit;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Global, read-only reference data — no company scope, any authenticated
 * user may read it (docs/plans/phase-1.md task 1.1).
 */
#[OA\Tag(name: 'Reference data')]
final class UnitsController
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/units', name: 'units_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'All units of measure.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'decimals', type: 'integer'),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<Unit> $units */
        $units = $this->handle(new ListUnits());

        return new JsonResponse(['items' => array_map(static fn (Unit $u) => [
            'code' => $u->code(),
            'name' => $u->name(),
            'decimals' => $u->decimals(),
        ], $units)]);
    }
}
