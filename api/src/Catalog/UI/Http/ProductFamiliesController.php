<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use App\Catalog\Application\Command\CreateProductFamily;
use App\Catalog\Application\Command\UpdateProductFamily;
use App\Catalog\Application\Query\ListProductFamilies;
use App\Catalog\Application\Query\ProductFamilyView;
use App\Catalog\Domain\ProductFamilyId;
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
 * checks (ADR 0002 groups families under the same pair as products) happen
 * in each handler. No delete endpoint: nothing in docs/plans/phase-1.md
 * task 1.6 calls for one, and `product_families` has no `active` column to
 * soft-delete with (unlike `products`).
 */
#[OA\Tag(name: 'Products')]
final class ProductFamiliesController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/product-families', name: 'product_families_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Product family created.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 404, description: 'parent_id does not name an existing family.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation.')]
    public function create(#[MapRequestPayload] ProductFamilyRequest $request, Request $httpRequest): Response
    {
        $familyId = ProductFamilyId::generate();

        $this->commandBus->dispatch(new CreateProductFamily(
            $familyId,
            $this->currentActorId->id(),
            $request->name,
            $this->parseNullableId($request->parent_id),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $familyId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/product-families', name: 'product_families_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Every product family for this company (a flat list; build the tree client-side from parent_id).', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'parent_id', type: 'string', format: 'uuid', nullable: true),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<ProductFamilyView> $families */
        $families = $this->handle(new ListProductFamilies());

        return new JsonResponse(['items' => array_map(static fn (ProductFamilyView $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'parent_id' => $f->parentId,
        ], $families)]);
    }

    #[Route('/api/v1/companies/{companyId}/product-families/{familyId}', name: 'product_families_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Product family updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 404, description: 'No such family, or parent_id does not name an existing family.')]
    #[OA\Response(response: 422, description: 'This would make the family tree cyclic, or the request payload failed validation.')]
    public function update(string $familyId, #[MapRequestPayload] ProductFamilyRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateProductFamily(
            $this->parseId($familyId),
            $this->currentActorId->id(),
            $request->name,
            $this->parseNullableId($request->parent_id),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    private function parseNullableId(?string $familyId): ?ProductFamilyId
    {
        return null === $familyId ? null : $this->parseId($familyId);
    }

    private function parseId(string $familyId): ProductFamilyId
    {
        try {
            return ProductFamilyId::fromString($familyId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such product family.');
        }
    }
}
