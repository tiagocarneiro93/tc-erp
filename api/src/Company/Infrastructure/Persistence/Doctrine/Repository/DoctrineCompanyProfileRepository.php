<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Doctrine\Repository;

use App\Company\Domain\CompanyProfile;
use App\Company\Domain\CompanyProfileRepository;
use App\Company\Domain\Exception\CompanyProfileNotFound;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\IssuerSnapshotProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineCompanyProfileRepository implements CompanyProfileRepository, IssuerSnapshotProvider
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId): ?CompanyProfile
    {
        return $this->entityManager->find(CompanyProfile::class, $companyId);
    }

    public function save(CompanyProfile $profile): void
    {
        $this->entityManager->persist($profile);
        $this->entityManager->flush();
    }

    public function snapshot(CompanyId $companyId): array
    {
        $profile = $this->find($companyId);

        if (null === $profile) {
            throw new CompanyProfileNotFound();
        }

        return [
            'nif' => (string) $profile->nif(),
            'legal_name' => $profile->legalName(),
            'commercial_name' => $profile->commercialName(),
            'address' => $profile->address(),
            'postal_code' => $profile->postalCode(),
            'city' => $profile->city(),
            'country' => $profile->country(),
            'fiscal_region' => $profile->fiscalRegion(),
        ];
    }
}
