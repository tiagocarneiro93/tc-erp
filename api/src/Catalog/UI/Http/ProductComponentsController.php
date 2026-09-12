<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use App\Catalog\Application\Command\RemoveProductComponent;
use App\Catalog\Application\Command\SetProductComponent;
use App\Catalog\Application\Query\KitComponentsView;
use App\Catalog\Application\Query\ListProductComponents;
use App\Catalog\Application\Query\ProductComponentView;
use App\Catalog\Domain\ProductId;
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
 * `CompanyRouteListener`; the `products.manage`/`products.read` permission
 * checks happen in each handler (docs/plans/phase-1.md task 1.8).
 */
#[OA\Tag(name: 'Products')]
final class ProductComponentsController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/products/{kitProductId}/components/{componentProductId}', name: 'product_components_set', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Component added or updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 404, description: 'No such kit product, or no such component product.')]
    #[OA\Response(response: 422, description: 'The kit product is not kind=kit, the component is itself a kit (nesting is not supported), an invalid quantity, or the request payload failed validation.')]
    public function set(string $kitProductId, string $componentProductId, #[MapRequestPayload] ProductComponentRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new SetProductComponent(
            $this->parseProductId($kitProductId),
            $this->parseProductId($componentProductId),
            $this->currentActorId->id(),
            $request->quantity,
            $request->sort_order,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/products/{kitProductId}/components/{componentProductId}', name: 'product_components_remove', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Component removed from the kit.')]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 404, description: 'No such component on this kit.')]
    public function remove(string $kitProductId, string $componentProductId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new RemoveProductComponent(
            $this->parseProductId($kitProductId),
            $this->parseProductId($componentProductId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/products/{kitProductId}/components', name: 'product_components_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'This kit\'s components, with an informational VAT-rate-mismatch flag per line and an estimated cost preview (null until Phase 5 populates average_cost).', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'component_product_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'quantity', type: 'string'),
            new OA\Property(property: 'sort_order', type: 'integer'),
            new OA\Property(property: 'vat_rate_mismatch', type: 'boolean'),
        ], type: 'object')),
        new OA\Property(property: 'estimated_cost', type: 'string', nullable: true),
    ]))]
    #[OA\Response(response: 404, description: 'No such product.')]
    public function list(string $kitProductId): JsonResponse
    {
        /** @var KitComponentsView $view */
        $view = $this->handle(new ListProductComponents($this->parseProductId($kitProductId)));

        return new JsonResponse([
            'items' => array_map(static fn (ProductComponentView $c) => [
                'component_product_id' => $c->componentProductId,
                'quantity' => $c->quantity,
                'sort_order' => $c->sortOrder,
                'vat_rate_mismatch' => $c->vatRateMismatch,
            ], $view->components),
            'estimated_cost' => $view->estimatedCost,
        ]);
    }

    private function parseProductId(string $productId): ProductId
    {
        try {
            return ProductId::fromString($productId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such product.');
        }
    }
}
