<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use App\Platform\Application\Command\CreateCompany;
use App\Platform\Application\Query\ListMyCompanies;
use App\Platform\Application\Query\MyCompanyView;
use App\Platform\Application\Security\CurrentUserId;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Http\CursorPaginator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Domain exceptions raised by command handlers are left to propagate to
 * {@see \App\Shared\Infrastructure\Http\ProblemDetailsExceptionListener}
 * (CLAUDE.md task 0.11).
 */
#[OA\Tag(name: 'Companies')]
final class CompaniesController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentUserId $currentUserId,
        private readonly CompanyContext $companyContext,
        private readonly CursorPaginator $paginator,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies', name: 'companies_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Company created; the caller becomes its owner.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
    ]))]
    #[OA\Response(response: 422, description: 'Invalid NIF or the request payload failed validation.')]
    #[OA\Response(response: 409, description: 'A company with this NIF already exists.')]
    public function create(#[MapRequestPayload] CreateCompanyRequest $request, Request $httpRequest): Response
    {
        $companyId = CompanyId::generate();
        $this->companyContext->set($companyId);

        try {
            $this->commandBus->dispatch(new CreateCompany(
                $this->currentUserId->id(),
                $request->nif,
                $request->legalName,
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));
        } finally {
            $this->companyContext->clear();
        }

        return new JsonResponse(['id' => $companyId->toString()], 201);
    }

    #[Route('/api/v1/companies', name: 'companies_list_mine', methods: ['GET'])]
    #[OA\Parameter(name: 'cursor', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Companies the caller is a member of.')]
    public function listMine(Request $request): JsonResponse
    {
        /** @var list<MyCompanyView> $companies */
        $companies = $this->handle(new ListMyCompanies($this->currentUserId->id()));

        $page = $this->paginator->paginate(
            $companies,
            $request->query->get('cursor'),
            $request->query->getInt('limit') ?: null,
            static fn (MyCompanyView $c): string => $c->companyId,
        );

        return new JsonResponse([
            'items' => array_map(static fn (MyCompanyView $c) => [
                'id' => $c->companyId,
                'nif' => $c->nif,
                'legal_name' => $c->legalName,
                'role' => $c->role,
            ], $page->items),
            'next_cursor' => $page->nextCursor,
        ]);
    }
}
