<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Command\CreateDraft;
use App\Fiscal\Application\Command\DeleteDraft;
use App\Fiscal\Application\Command\UpdateDraft;
use App\Fiscal\Application\Query\DraftValidationError;
use App\Fiscal\Application\Query\DraftView;
use App\Fiscal\Application\Query\GetDraft;
use App\Fiscal\Application\Query\ListDrafts;
use App\Fiscal\Application\Query\ValidateDraft;
use App\Fiscal\Domain\DocumentDraftId;
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
 * `CompanyRouteListener`; `documents.issue`/`documents.read` permission
 * checks happen in each handler (docs/plans/phase-2.md task 2.4). CLAUDE.md
 * hard rule: drafts are never printable or sendable — there is no such
 * route here, and none of `calculated`'s callers treat it as anything but
 * a preview.
 */
#[OA\Tag(name: 'Drafts')]
final class DraftsController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/drafts', name: 'drafts_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Draft created (status draft).', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 422, description: 'The request payload failed validation, or document_type is unknown.')]
    public function create(#[MapRequestPayload] CreateDraftRequest $request, Request $httpRequest): Response
    {
        $draftId = DocumentDraftId::generate();

        $this->commandBus->dispatch(new CreateDraft(
            $draftId,
            $this->currentActorId->id(),
            $request->document_type,
            $request->payload,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(['id' => $draftId->toString()], 201);
    }

    #[Route('/api/v1/companies/{companyId}/drafts', name: 'drafts_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Every draft for this company, most recently updated first.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'document_type', type: 'string'),
            new OA\Property(property: 'payload', type: 'object'),
            new OA\Property(property: 'calculated', type: 'object', nullable: true),
            new OA\Property(property: 'series_id', type: 'string', nullable: true),
            new OA\Property(property: 'customer_id', type: 'string', nullable: true),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'mention', type: 'string', nullable: true, description: 'Despacho 8632/2014 §1.2 — set for working documents (OR/PF/NE).'),
        ], type: 'object')),
    ]))]
    public function list(): JsonResponse
    {
        /** @var list<DraftView> $drafts */
        $drafts = $this->handle(new ListDrafts());

        return new JsonResponse(['items' => array_map(self::toArray(...), $drafts)]);
    }

    #[Route('/api/v1/companies/{companyId}/drafts/{draftId}', name: 'drafts_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The draft.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'document_type', type: 'string'),
        new OA\Property(property: 'payload', type: 'object'),
        new OA\Property(property: 'calculated', type: 'object', nullable: true),
        new OA\Property(property: 'series_id', type: 'string', nullable: true),
        new OA\Property(property: 'customer_id', type: 'string', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'mention', type: 'string', nullable: true, description: 'Despacho 8632/2014 §1.2 — set for working documents (OR/PF/NE).'),
    ]))]
    #[OA\Response(response: 404, description: 'No such draft.')]
    public function get(string $draftId): JsonResponse
    {
        /** @var DraftView $draft */
        $draft = $this->handle(new GetDraft($this->parseId($draftId)));

        return new JsonResponse(self::toArray($draft));
    }

    #[Route('/api/v1/companies/{companyId}/drafts/{draftId}', name: 'drafts_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Draft updated; calculated is recomputed live.')]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'No such draft.')]
    public function update(string $draftId, #[MapRequestPayload] UpdateDraftRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateDraft(
            $this->parseId($draftId),
            $this->currentActorId->id(),
            $request->payload,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/drafts/{draftId}', name: 'drafts_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Draft deleted — a real delete, drafts carry no fiscal weight.')]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'No such draft.')]
    public function delete(string $draftId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new DeleteDraft(
            $this->parseId($draftId),
            $this->currentActorId->id(),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/drafts/{draftId}/validate', name: 'drafts_validate', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Validation errors for issuing this draft (empty when none) — reused by task 2.6\'s issuance use case.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'field', type: 'string'),
            new OA\Property(property: 'message', type: 'string'),
        ], type: 'object')),
    ]))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.issue permission.')]
    #[OA\Response(response: 404, description: 'No such draft.')]
    public function validate(string $draftId): JsonResponse
    {
        /** @var list<DraftValidationError> $errors */
        $errors = $this->handle(new ValidateDraft($this->parseId($draftId)));

        return new JsonResponse(['errors' => array_map(
            static fn (DraftValidationError $e): array => ['field' => $e->field, 'message' => $e->message],
            $errors,
        )]);
    }

    /**
     * @return array{id: string, document_type: string, payload: array<string, mixed>, calculated: ?array<string, mixed>, series_id: ?string, customer_id: ?string, updated_at: string, mention: ?string}
     */
    private static function toArray(DraftView $draft): array
    {
        return [
            'id' => $draft->id,
            'document_type' => $draft->documentType,
            'payload' => $draft->payload,
            'calculated' => $draft->calculated,
            'series_id' => $draft->seriesId,
            'customer_id' => $draft->customerId,
            'updated_at' => $draft->updatedAt,
            'mention' => $draft->mention,
        ];
    }

    private function parseId(string $draftId): DocumentDraftId
    {
        try {
            return DocumentDraftId::fromString($draftId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such draft.');
        }
    }
}
