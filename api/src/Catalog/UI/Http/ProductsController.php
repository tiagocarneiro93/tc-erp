<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use App\Catalog\Application\Command\CreateProduct;
use App\Catalog\Application\Command\DeactivateProduct;
use App\Catalog\Application\Command\UpdateProduct;
use App\Catalog\Application\Query\GetProduct;
use App\Catalog\Application\Query\ListProducts;
use App\Catalog\Application\Query\ProductView;
use App\Catalog\Domain\ProductFamilyId;
use App\Catalog\Domain\ProductId;
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
 * `CompanyRouteListener`; the `products.manage`/`products.read` permission
 * checks happen in each handler (docs/plans/phase-1.md task 1.6).
 */
#[OA\Tag(name: 'Products')]
final class ProductsController
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

    #[Route('/api/v1/companies/{companyId}/products', name: 'products_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Product created.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 404, description: 'family_id does not name an existing family.')]
    #[OA\Response(response: 422, description: 'Invalid type, kind, unit, tax rate, exemption reason, or the request payload failed validation.')]
    public function create(#[MapRequestPayload] ProductRequest $request, Request $httpRequest): Response
    {
        $productId = ProductId::generate();

        $this->commandBus->dispatch(new CreateProduct(
            $productId,
            $this->currentActorId->id(),
            $request->code,
            $request->description,
            $request->type,
            $request->kind,
            $request->unit_code,
            $request->barcode,
            $this->parseNullableFamilyId($request->family_id),
            $request->tax_rate_id,
            $request->exemption_reason_code,
            $request->track_stock,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $productId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/products', name: 'products_list', methods: ['GET'])]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'family_id', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'active', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'track_stock', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'cursor', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Products matching the filters, if any.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'description', type: 'string'),
            new OA\Property(property: 'type', type: 'string'),
            new OA\Property(property: 'kind', type: 'string'),
            new OA\Property(property: 'unit_code', type: 'string'),
            new OA\Property(property: 'barcode', type: 'string', nullable: true),
            new OA\Property(property: 'family_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'tax_rate_id', type: 'string'),
            new OA\Property(property: 'exemption_reason_code', type: 'string', nullable: true),
            new OA\Property(property: 'track_stock', type: 'boolean'),
            new OA\Property(property: 'active', type: 'boolean'),
            new OA\Property(property: 'last_cost', type: 'string', nullable: true),
            new OA\Property(property: 'average_cost', type: 'string', nullable: true),
        ], type: 'object')),
        new OA\Property(property: 'next_cursor', type: 'string', nullable: true),
    ]))]
    public function list(Request $request): JsonResponse
    {
        /** @var list<ProductView> $products */
        $products = $this->handle(new ListProducts(
            $request->query->get('search'),
            $this->parseNullableFamilyId($request->query->get('family_id')),
            $this->parseNullableBool($request->query->get('active')),
            $this->parseNullableBool($request->query->get('track_stock')),
        ));

        $page = $this->paginator->paginate(
            $products,
            $request->query->get('cursor'),
            $request->query->getInt('limit') ?: null,
            static fn (ProductView $p): string => $p->id,
        );

        return new JsonResponse([
            'items' => array_map(self::toArray(...), $page->items),
            'next_cursor' => $page->nextCursor,
        ]);
    }

    #[Route('/api/v1/companies/{companyId}/products/{productId}', name: 'products_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The product.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'kind', type: 'string'),
        new OA\Property(property: 'unit_code', type: 'string'),
        new OA\Property(property: 'barcode', type: 'string', nullable: true),
        new OA\Property(property: 'family_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'tax_rate_id', type: 'string'),
        new OA\Property(property: 'exemption_reason_code', type: 'string', nullable: true),
        new OA\Property(property: 'track_stock', type: 'boolean'),
        new OA\Property(property: 'active', type: 'boolean'),
        new OA\Property(property: 'last_cost', type: 'string', nullable: true),
        new OA\Property(property: 'average_cost', type: 'string', nullable: true),
    ]))]
    #[OA\Response(response: 404, description: 'No such product.')]
    public function get(string $productId): JsonResponse
    {
        /** @var ProductView $product */
        $product = $this->handle(new GetProduct($this->parseProductId($productId)));

        return new JsonResponse(self::toArray($product));
    }

    #[Route('/api/v1/companies/{companyId}/products/{productId}', name: 'products_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Product updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 404, description: 'No such product, or family_id does not name an existing family.')]
    #[OA\Response(response: 422, description: 'Invalid type, unit, tax rate, exemption reason, or the request payload failed validation.')]
    public function update(string $productId, #[MapRequestPayload] ProductRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateProduct(
            $this->parseProductId($productId),
            $this->currentActorId->id(),
            $request->code,
            $request->description,
            $request->type,
            $request->unit_code,
            $request->barcode,
            $this->parseNullableFamilyId($request->family_id),
            $request->tax_rate_id,
            $request->exemption_reason_code,
            $request->track_stock,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/products/{productId}', name: 'products_deactivate', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Product deactivated (active = false; not a hard delete).')]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 404, description: 'No such product.')]
    public function deactivate(string $productId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new DeactivateProduct(
            $this->parseProductId($productId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    /**
     * @return array{id: string, code: string, description: string, type: string, kind: string, unit_code: string, barcode: ?string, family_id: ?string, tax_rate_id: string, exemption_reason_code: ?string, track_stock: bool, active: bool, last_cost: ?string, average_cost: ?string}
     */
    private static function toArray(ProductView $product): array
    {
        return [
            'id' => $product->id,
            'code' => $product->code,
            'description' => $product->description,
            'type' => $product->type,
            'kind' => $product->kind,
            'unit_code' => $product->unitCode,
            'barcode' => $product->barcode,
            'family_id' => $product->familyId,
            'tax_rate_id' => $product->taxRateId,
            'exemption_reason_code' => $product->exemptionReasonCode,
            'track_stock' => $product->trackStock,
            'active' => $product->active,
            'last_cost' => $product->lastCost,
            'average_cost' => $product->averageCost,
        ];
    }

    private function parseNullableFamilyId(?string $familyId): ?ProductFamilyId
    {
        if (null === $familyId) {
            return null;
        }

        try {
            return ProductFamilyId::fromString($familyId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such product family.');
        }
    }

    private function parseNullableBool(?string $value): ?bool
    {
        if (null === $value) {
            return null;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOLEAN);
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
