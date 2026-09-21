<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Command\ActivateSeries;
use App\Fiscal\Application\Command\CancelSeries;
use App\Fiscal\Application\Command\CreateSeries;
use App\Fiscal\Application\Command\FinishSeries;
use App\Fiscal\Application\Command\UpdateSeries;
use App\Fiscal\Application\Query\GetSeries;
use App\Fiscal\Application\Query\ListSeries;
use App\Fiscal\Application\Query\SeriesView;
use App\Fiscal\Domain\SeriesId;
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
 * `CompanyRouteListener`; the `series.manage` permission check happens in
 * each handler (docs/plans/phase-2.md task 2.2). Plain list, no cursor
 * pagination — a company's series are few, same reasoning as
 * `WarehousesController`.
 */
#[OA\Tag(name: 'Series')]
final class SeriesController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/series', name: 'series_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Series created (status draft).', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the series.manage permission.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation, or document_type is unknown.')]
    public function create(#[MapRequestPayload] CreateSeriesRequest $request, Request $httpRequest): Response
    {
        $seriesId = SeriesId::generate();

        $this->commandBus->dispatch(new CreateSeries(
            $seriesId,
            $this->currentActorId->id(),
            $request->document_type,
            $request->code,
            $request->is_training,
            $request->first_number,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $seriesId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/series', name: 'series_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Every series for this company.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'document_type', type: 'string'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'is_training', type: 'boolean'),
            new OA\Property(property: 'validation_code', type: 'string', nullable: true),
            new OA\Property(property: 'status', type: 'string', enum: ['draft', 'active', 'finished', 'cancelled']),
            new OA\Property(property: 'first_number', type: 'integer'),
            new OA\Property(property: 'last_number', type: 'integer', nullable: true),
            new OA\Property(property: 'can_issue', type: 'boolean'),
            new OA\Property(property: 'at_communicated_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'at_finished_at', type: 'string', format: 'date-time', nullable: true),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<SeriesView> $series */
        $series = $this->handle(new ListSeries());

        return new JsonResponse(['items' => array_map(self::toArray(...), $series)]);
    }

    #[Route('/api/v1/companies/{companyId}/series/{seriesId}', name: 'series_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The series.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'document_type', type: 'string'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'is_training', type: 'boolean'),
        new OA\Property(property: 'validation_code', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'active', 'finished', 'cancelled']),
        new OA\Property(property: 'first_number', type: 'integer'),
        new OA\Property(property: 'last_number', type: 'integer', nullable: true),
        new OA\Property(property: 'can_issue', type: 'boolean'),
        new OA\Property(property: 'at_communicated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'at_finished_at', type: 'string', format: 'date-time', nullable: true),
    ]))]
    #[OA\Response(response: 404, description: 'No such series.')]
    public function get(string $seriesId): JsonResponse
    {
        /** @var SeriesView $series */
        $series = $this->handle(new GetSeries($this->parseId($seriesId)));

        return new JsonResponse(self::toArray($series));
    }

    #[Route('/api/v1/companies/{companyId}/series/{seriesId}', name: 'series_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Series updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the series.manage permission.')]
    #[OA\Response(response: 404, description: 'No such series.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation, or the series is not draft.')]
    public function update(string $seriesId, #[MapRequestPayload] UpdateSeriesRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateSeries(
            $this->parseId($seriesId),
            $this->currentActorId->id(),
            $request->code,
            $request->is_training,
            $request->first_number,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/series/{seriesId}/activate', name: 'series_activate', methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Series activated (status active).')]
    #[OA\Response(response: 403, description: 'The caller lacks the series.manage permission.')]
    #[OA\Response(response: 404, description: 'No such series.')]
    #[OA\Response(response: 422, description: 'The series is not draft, so it cannot be activated.')]
    public function activate(string $seriesId, #[MapRequestPayload] ActivateSeriesRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new ActivateSeries(
            $this->parseId($seriesId),
            $this->currentActorId->id(),
            $request->validation_code,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/series/{seriesId}/finish', name: 'series_finish', methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Series finished (status finished).')]
    #[OA\Response(response: 403, description: 'The caller lacks the series.manage permission.')]
    #[OA\Response(response: 404, description: 'No such series.')]
    #[OA\Response(response: 422, description: 'The series is not active, so it cannot be finished.')]
    public function finish(string $seriesId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new FinishSeries(
            $this->parseId($seriesId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/series/{seriesId}/cancel', name: 'series_cancel', methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Series cancelled (status cancelled).')]
    #[OA\Response(response: 403, description: 'The caller lacks the series.manage permission.')]
    #[OA\Response(response: 404, description: 'No such series.')]
    #[OA\Response(response: 422, description: 'The series is already finished or cancelled.')]
    public function cancel(string $seriesId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new CancelSeries(
            $this->parseId($seriesId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    /**
     * @return array{id: string, document_type: string, code: string, is_training: bool, validation_code: ?string, status: string, first_number: int, last_number: ?int, can_issue: bool, at_communicated_at: ?string, at_finished_at: ?string}
     */
    private static function toArray(SeriesView $series): array
    {
        return [
            'id' => $series->id,
            'document_type' => $series->documentType,
            'code' => $series->code,
            'is_training' => $series->isTraining,
            'validation_code' => $series->validationCode,
            'status' => $series->status,
            'first_number' => $series->firstNumber,
            'last_number' => $series->lastNumber,
            'can_issue' => $series->canIssue,
            'at_communicated_at' => $series->atCommunicatedAt,
            'at_finished_at' => $series->atFinishedAt,
        ];
    }

    private function parseId(string $seriesId): SeriesId
    {
        try {
            return SeriesId::fromString($seriesId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such series.');
        }
    }
}
