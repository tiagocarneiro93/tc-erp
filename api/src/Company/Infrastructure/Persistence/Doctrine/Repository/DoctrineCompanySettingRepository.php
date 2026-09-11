<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Doctrine\Repository;

use App\Company\Domain\CompanySetting;
use App\Company\Domain\CompanySettingRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineCompanySettingRepository implements CompanySettingRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, string $key): ?CompanySetting
    {
        return $this->entityManager->find(CompanySetting::class, ['companyId' => $companyId, 'key' => $key]);
    }

    public function save(CompanySetting $setting): void
    {
        $this->entityManager->persist($setting);
        $this->entityManager->flush();
    }
}
