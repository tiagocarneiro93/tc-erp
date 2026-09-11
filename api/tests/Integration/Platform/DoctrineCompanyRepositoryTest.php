<?php

declare(strict_types=1);

namespace App\Tests\Integration\Platform;

use App\Platform\Domain\Company;
use App\Platform\Domain\CompanyRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;

final class DoctrineCompanyRepositoryTest extends PlatformRepositoryTestCase
{
    public function testSaveAndFindRoundTrip(): void
    {
        /** @var CompanyRepository $repository */
        $repository = self::getContainer()->get(CompanyRepository::class);

        $id = CompanyId::generate();
        $nif = Nif::fromString('123456789');
        $company = Company::register($id, $nif, 'Acme, Lda.', new \DateTimeImmutable());
        $repository->save($company);
        $this->entityManager->clear();

        $found = $repository->find($id);

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->id()));
        self::assertTrue($nif->equals($found->nif()));
        self::assertSame('Acme, Lda.', $found->legalName());
        self::assertSame('active', $found->status());
    }

    public function testFindByNif(): void
    {
        /** @var CompanyRepository $repository */
        $repository = self::getContainer()->get(CompanyRepository::class);

        $nif = Nif::fromString('999999990');
        $company = Company::register(CompanyId::generate(), $nif, 'Consumidor Final Holding', new \DateTimeImmutable());
        $repository->save($company);
        $this->entityManager->clear();

        self::assertNotNull($repository->findByNif($nif));
        self::assertNull($repository->findByNif(Nif::fromString('123456789')));
    }
}
