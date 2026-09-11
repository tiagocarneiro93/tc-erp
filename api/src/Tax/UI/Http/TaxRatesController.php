<?php

declare(strict_types=1);

namespace App\Tax\UI\Http;

use App\Tax\Application\Query\ListTaxRates;
use App\Tax\Domain\TaxRate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Global, read-only reference data — no company scope, any authenticated
 * user may read it (docs/plans/phase-1.md task 1.2). PT-AC/PT-MA rows are
 * an owner-supplied candidate value, not yet confirmed against the
 * regional decree (docs/legal/civa-extracts.md).
 */
#[OA\Tag(name: 'Reference data')]
final class TaxRatesController
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/tax-rates', name: 'tax_rates_list', methods: ['GET'])]
    #[OA\Parameter(name: 'region', in: 'query', schema: new OA\Schema(type: 'string'), description: 'PT|PT-AC|PT-MA')]
    #[OA\Parameter(name: 'as_of', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Only rates valid on this date; omit to list every rate ever seeded.')]
    #[OA\Response(response: 200, description: 'Tax rates.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'region', type: 'string'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'percentage', type: 'string'),
            new OA\Property(property: 'valid_from', type: 'string', format: 'date'),
            new OA\Property(property: 'valid_to', type: 'string', format: 'date', nullable: true),
            new OA\Property(property: 'description', type: 'string'),
        ], type: 'object')),
    ]))]
    #[OA\Response(response: 400, description: 'Invalid as_of date.')]
    public function list(Request $request): JsonResponse
    {
        $region = $request->query->get('region');
        $asOf = $this->parseAsOf($request->query->get('as_of'));

        /** @var list<TaxRate> $rates */
        $rates = $this->handle(new ListTaxRates($region, $asOf));

        return new JsonResponse(['items' => array_map(static fn (TaxRate $r) => [
            'id' => $r->id()->toString(),
            'region' => $r->region(),
            'code' => $r->code(),
            'percentage' => $r->percentage()->toString(),
            'valid_from' => $r->validFrom()->format('Y-m-d'),
            'valid_to' => $r->validTo()?->format('Y-m-d'),
            'description' => $r->description(),
        ], $rates)]);
    }

    private function parseAsOf(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new BadRequestHttpException('Invalid as_of date.');
        }
    }
}
