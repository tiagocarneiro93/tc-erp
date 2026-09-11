<?php

declare(strict_types=1);

namespace App\Company\UI\Http;

use App\Company\Application\Command\UpdateCompanyProfile;
use App\Company\Application\Query\CompanyProfileView;
use App\Company\Application\Query\GetCompanyProfile;
use App\Shared\Domain\Security\CurrentActorId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * `{companyId}` is resolved and authorized (membership) by
 * `CompanyRouteListener`; the `company.manage` permission check happens in
 * `UpdateCompanyProfileHandler` (docs/plans/phase-1.md task 1.4).
 */
#[OA\Tag(name: 'Company')]
final class CompanyProfileController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/profile', name: 'company_profile_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'This company\'s fiscal profile.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'nif', type: 'string'),
        new OA\Property(property: 'legal_name', type: 'string'),
        new OA\Property(property: 'commercial_name', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'postal_code', type: 'string', nullable: true),
        new OA\Property(property: 'city', type: 'string', nullable: true),
        new OA\Property(property: 'country', type: 'string'),
        new OA\Property(property: 'share_capital', type: 'string', nullable: true),
        new OA\Property(property: 'registry_office', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'logo_key', type: 'string', nullable: true),
        new OA\Property(property: 'fiscal_region', type: 'string'),
        new OA\Property(property: 'vat_regime', type: 'string'),
        new OA\Property(property: 'cash_vat', type: 'boolean'),
    ]))]
    public function get(): JsonResponse
    {
        /** @var CompanyProfileView $profile */
        $profile = $this->handle(new GetCompanyProfile());

        return new JsonResponse([
            'nif' => $profile->nif,
            'legal_name' => $profile->legalName,
            'commercial_name' => $profile->commercialName,
            'address' => $profile->address,
            'postal_code' => $profile->postalCode,
            'city' => $profile->city,
            'country' => $profile->country,
            'share_capital' => $profile->shareCapital,
            'registry_office' => $profile->registryOffice,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'logo_key' => $profile->logoKey,
            'fiscal_region' => $profile->fiscalRegion,
            'vat_regime' => $profile->vatRegime,
            'cash_vat' => $profile->cashVat,
        ]);
    }

    #[Route('/api/v1/companies/{companyId}/profile', name: 'company_profile_update', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Profile updated.')]
    #[OA\Response(response: 403, description: 'The caller lacks the company.manage permission.')]
    #[OA\Response(response: 404, description: 'The caller is not a member of this company.')]
    #[OA\Response(response: 422, description: 'Invalid NIF, country, fiscal region, or the request payload failed validation.')]
    public function update(#[MapRequestPayload] UpdateCompanyProfileRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new UpdateCompanyProfile(
            $this->currentActorId->id(),
            $request->nif,
            $request->legal_name,
            $request->commercial_name,
            $request->address,
            $request->postal_code,
            $request->city,
            $request->country,
            $request->share_capital,
            $request->registry_office,
            $request->email,
            $request->phone,
            $request->logo_key,
            $request->fiscal_region,
            $request->vat_regime,
            $request->cash_vat,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }
}
