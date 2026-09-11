<?php

declare(strict_types=1);

namespace App\Tax\UI\Http;

use App\Tax\Application\Query\ListExemptionReasons;
use App\Tax\Domain\ExemptionReason;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Global, read-only reference data — no company scope, any authenticated
 * user may read it (docs/plans/phase-1.md task 1.2). Source:
 * docs/legal/at-tabela-codigos-motivo-isencao.pdf (V4.0, 18 Jun 2026).
 */
#[OA\Tag(name: 'Reference data')]
final class ExemptionReasonsController
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/exemption-reasons', name: 'exemption_reasons_list', methods: ['GET'])]
    #[OA\Parameter(name: 'as_of', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Only reasons valid on this date; omit to list every reason ever seeded.')]
    #[OA\Response(response: 200, description: 'VAT exemption/non-liquidation reason codes.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'description', type: 'string'),
            new OA\Property(property: 'legal_reference', type: 'string'),
            new OA\Property(property: 'valid_from', type: 'string', format: 'date'),
            new OA\Property(property: 'valid_to', type: 'string', format: 'date', nullable: true),
        ], type: 'object')),
    ]))]
    #[OA\Response(response: 400, description: 'Invalid as_of date.')]
    public function list(Request $request): JsonResponse
    {
        $asOf = $this->parseAsOf($request->query->get('as_of'));

        /** @var list<ExemptionReason> $reasons */
        $reasons = $this->handle(new ListExemptionReasons($asOf));

        return new JsonResponse(['items' => array_map(static fn (ExemptionReason $r) => [
            'code' => $r->code(),
            'description' => $r->description(),
            'legal_reference' => $r->legalReference(),
            'valid_from' => $r->validFrom()->format('Y-m-d'),
            'valid_to' => $r->validTo()?->format('Y-m-d'),
        ], $reasons)]);
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
