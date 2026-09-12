<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use App\Catalog\Application\Command\CreatePriceList;
use App\Catalog\Application\Query\ListPriceLists;
use App\Catalog\Application\Query\PriceListView;
use App\Catalog\Domain\PriceListId;
use App\Shared\Domain\Security\CurrentActorId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * `{companyId}` is resolved and authorized (membership) by
 * `CompanyRouteListener`; the `products.manage`/`products.read` permission
 * checks (ADR 0002 groups price lists under the same pair as products)
 * happen in each handler (docs/plans/phase-1.md task 1.7). No update/delete
 * endpoint: nothing in this task calls for one.
 */
#[OA\Tag(name: 'Products')]
final class PriceListsController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/price-lists', name: 'price_lists_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Price list created.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation.')]
    public function create(#[MapRequestPayload] PriceListRequest $request, Request $httpRequest): Response
    {
        $priceListId = PriceListId::generate();

        $this->commandBus->dispatch(new CreatePriceList(
            $priceListId,
            $this->currentActorId->id(),
            $request->name,
            $request->default_includes_vat,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $priceListId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/price-lists', name: 'price_lists_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Every price list for this company.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'default_includes_vat', type: 'boolean'),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<PriceListView> $priceLists */
        $priceLists = $this->handle(new ListPriceLists());

        return new JsonResponse(['items' => array_map(static fn (PriceListView $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'default_includes_vat' => $p->defaultIncludesVat,
        ], $priceLists)]);
    }
}
