<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use App\Catalog\Application\Command\SetProductPrice;
use App\Catalog\Application\Query\CalculateProductPriceConversion;
use App\Catalog\Application\Query\ListProductPrices;
use App\Catalog\Application\Query\PriceConversionResult;
use App\Catalog\Application\Query\ProductPriceView;
use App\Catalog\Domain\PriceListId;
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
 * checks happen in each handler (docs/plans/phase-1.md task 1.7). `/calculate`
 * is display-only — the request body it receives is never persisted, that's
 * what the `PUT .../prices/{priceListId}` endpoint is for.
 */
#[OA\Tag(name: 'Products')]
final class ProductPricesController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/products/{productId}/prices/{priceListId}', name: 'product_prices_set', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Price set (stored exactly as entered — never a converted value).')]
    #[OA\Response(response: 403, description: 'The caller lacks the products.manage permission.')]
    #[OA\Response(response: 404, description: 'No such product, or no such price list.')]
    #[OA\Response(response: 422, description: 'Invalid amount, or the request payload failed validation.')]
    public function set(string $productId, string $priceListId, #[MapRequestPayload] ProductPriceRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new SetProductPrice(
            $this->parseProductId($productId),
            $this->parsePriceListId($priceListId),
            $this->currentActorId->id(),
            $request->amount,
            $request->includes_vat,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/products/{productId}/prices', name: 'product_prices_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'This product\'s price in every price list it has one in.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'price_list_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'amount', type: 'string'),
            new OA\Property(property: 'includes_vat', type: 'boolean'),
        ], type: 'object')),
    ]))]
    #[OA\Response(response: 404, description: 'No such product.')]
    public function list(string $productId): JsonResponse
    {
        /** @var list<ProductPriceView> $prices */
        $prices = $this->handle(new ListProductPrices($this->parseProductId($productId)));

        return new JsonResponse(['items' => array_map(static fn (ProductPriceView $p) => [
            'price_list_id' => $p->priceListId,
            'amount' => $p->amount,
            'includes_vat' => $p->includesVat,
        ], $prices)]);
    }

    #[Route('/api/v1/companies/{companyId}/products/{productId}/prices/calculate', name: 'product_prices_calculate', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The other-mode value for the given amount (display only, not persisted).', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'amount', type: 'string'),
        new OA\Property(property: 'includes_vat', type: 'boolean'),
        new OA\Property(property: 'converted_amount', type: 'string'),
        new OA\Property(property: 'converted_includes_vat', type: 'boolean'),
    ]))]
    #[OA\Response(response: 404, description: 'No such product.')]
    #[OA\Response(response: 422, description: 'Invalid amount, or the request payload failed validation.')]
    public function calculate(string $productId, #[MapRequestPayload] PriceConversionRequest $request): JsonResponse
    {
        /** @var PriceConversionResult $result */
        $result = $this->handle(new CalculateProductPriceConversion(
            $this->parseProductId($productId),
            $request->amount,
            $request->includes_vat,
        ));

        return new JsonResponse([
            'amount' => $result->amount,
            'includes_vat' => $result->includesVat,
            'converted_amount' => $result->convertedAmount,
            'converted_includes_vat' => $result->convertedIncludesVat,
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

    private function parsePriceListId(string $priceListId): PriceListId
    {
        try {
            return PriceListId::fromString($priceListId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such price list.');
        }
    }
}
