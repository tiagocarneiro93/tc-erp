<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use App\Platform\Application\Command\CreateCompany;
use App\Platform\Application\Query\ListMyCompanies;
use App\Platform\Application\Query\MyCompanyView;
use App\Platform\Application\Security\CurrentUserId;
use App\Platform\Domain\Exception\CompanyAlreadyExists;
use App\Platform\Domain\Exception\InvalidNif;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class CompaniesController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentUserId $currentUserId,
        private readonly CompanyContext $companyContext,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies', name: 'companies_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateCompanyRequest $request, Request $httpRequest): Response
    {
        $companyId = CompanyId::generate();
        $this->companyContext->set($companyId);

        try {
            $this->dispatchCommand(new CreateCompany(
                $this->currentUserId->id(),
                $request->nif,
                $request->legalName,
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));
        } catch (InvalidNif $e) {
            return $this->problem($e->getMessage(), 422);
        } catch (CompanyAlreadyExists $e) {
            return $this->problem($e->getMessage(), 409);
        } finally {
            $this->companyContext->clear();
        }

        return new JsonResponse(['id' => $companyId->toString()], 201);
    }

    #[Route('/api/v1/companies', name: 'companies_list_mine', methods: ['GET'])]
    public function listMine(): JsonResponse
    {
        /** @var list<MyCompanyView> $companies */
        $companies = $this->handle(new ListMyCompanies($this->currentUserId->id()));

        return new JsonResponse(['companies' => array_map(static fn (MyCompanyView $c) => [
            'id' => $c->companyId,
            'nif' => $c->nif,
            'legal_name' => $c->legalName,
            'role' => $c->role,
        ], $companies)]);
    }

    /**
     * Messenger wraps handler exceptions in HandlerFailedException; unwrap
     * so callers can catch the real domain exception.
     */
    private function dispatchCommand(object $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $wrapped) {
                throw $wrapped;
            }

            throw $e;
        }
    }

    private function problem(string $detail, int $status): JsonResponse
    {
        return new JsonResponse(
            ['title' => $detail, 'status' => $status],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
