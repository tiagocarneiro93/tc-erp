<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Application\Query\ListCountries;
use App\Shared\Domain\Country;
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
final class CountriesController
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/countries', name: 'countries_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'All countries.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'code', type: 'string', description: 'ISO 3166-1 alpha-2'),
            new OA\Property(property: 'name', type: 'string'),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<Country> $countries */
        $countries = $this->handle(new ListCountries());

        return new JsonResponse(['items' => array_map(static fn (Country $c) => [
            'code' => $c->code(),
            'name' => $c->name(),
        ], $countries)]);
    }
}
