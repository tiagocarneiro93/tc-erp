<?php

declare(strict_types=1);

namespace App\Tests\Integration\Company;

use App\Company\Domain\AtCredentials;
use App\Company\Domain\AtCredentialsRepository;
use App\Company\Domain\CompanyProfile;
use App\Company\Domain\CompanyProfileRepository;
use App\Company\Domain\CompanySetting;
use App\Company\Domain\CompanySettingRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * technical-scope.md §5.2/§5.3, docs/plans/phase-1.md task 1.4: proves RLS
 * actually isolates the three new company-scoped tables through the same
 * repositories the application uses, not just the generic schema check
 * ({@see \App\Tests\Integration\Shared\CompanyIsolationSchemaTest}, which
 * already covers every `company_id` table automatically).
 *
 * Every cross-company read below clears the EntityManager's identity map
 * first: otherwise `find()` would silently return the PHP object already
 * cached from the write moments earlier in the same test, without issuing
 * a second SQL query at all — proving nothing about RLS.
 */
final class CompanyModuleIsolationTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RequestCompanyContext $companyContext;
    private CompanyProfileRepository $profiles;
    private CompanySettingRepository $settings;
    private AtCredentialsRepository $credentials;

    /** @var list<CompanyId> */
    private array $companiesToCleanUp = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->entityManager = $entityManager;
        /** @var RequestCompanyContext $context */
        $context = self::getContainer()->get(RequestCompanyContext::class);
        $this->companyContext = $context;
        /** @var CompanyProfileRepository $profiles */
        $profiles = self::getContainer()->get(CompanyProfileRepository::class);
        $this->profiles = $profiles;
        /** @var CompanySettingRepository $settings */
        $settings = self::getContainer()->get(CompanySettingRepository::class);
        $this->settings = $settings;
        /** @var AtCredentialsRepository $credentials */
        $credentials = self::getContainer()->get(AtCredentialsRepository::class);
        $this->credentials = $credentials;
    }

    protected function tearDown(): void
    {
        $this->companyContext->clear();

        foreach ($this->companiesToCleanUp as $companyId) {
            $this->companyContext->set($companyId);
            $this->connection->beginTransaction();
            $this->connection->executeStatement('DELETE FROM company_profile WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->executeStatement('DELETE FROM settings WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->executeStatement('DELETE FROM at_credentials WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->commit();
            $this->companyContext->clear();
        }

        parent::tearDown();
    }

    public function testACompanyCannotReadAnotherCompanysProfile(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->profiles->save(CompanyProfile::createDefault($companyA, $this->uniqueNif(), 'Company A'));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $this->profiles->save(CompanyProfile::createDefault($companyB, $this->uniqueNif(), 'Company B'));
        $seenAsB = $this->profiles->find($companyA);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertNull($seenAsB, 'Company B must not see company A\'s profile row.');
    }

    public function testACompanyCannotWriteASettingsRowForAnotherCompany(): void
    {
        $companyA = $this->newCompany();
        $companyB = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyB;

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();

        try {
            $this->expectException(DbalException::class);
            // RLS's WITH CHECK rejects a row whose company_id does not
            // match the transaction's context, however it got there —
            // simulated with a direct insert bypassing the repository.
            $this->connection->insert('settings', [
                'company_id' => $companyB->toString(),
                'key' => 'probe',
                'value' => '{}',
            ], ['value' => 'jsonb']);
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }
    }

    public function testACompanyCannotReadAnotherCompanysAtCredentials(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->credentials->save(AtCredentials::create($companyA, '502757191/1', 'ciphertext'));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->credentials->find($companyA);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertNull($seenAsB, 'Company B must not see company A\'s AT credentials.');
    }

    public function testACompanySettingIsOnlyVisibleWithinItsOwnCompany(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->settings->save(new CompanySetting($companyA, 'default_warehouse', ['value' => 'main']));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->settings->find($companyA, 'default_warehouse');
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertNull($seenAsB, 'Company B must not see company A\'s setting row.');
    }

    private function newCompany(): CompanyId
    {
        $companyId = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyId;

        return $companyId;
    }

    private function uniqueNif(): Nif
    {
        do {
            $prefix = (string) random_int(10_000_000, 99_999_999);
            $sum = 0;
            for ($position = 0; $position < 8; ++$position) {
                $sum += (int) $prefix[$position] * (9 - $position);
            }
            $remainder = $sum % 11;
            $checkDigit = $remainder < 2 ? 0 : 11 - $remainder;
            $nif = $prefix.$checkDigit;
        } while (!Nif::isValid($nif));

        return Nif::fromString($nif);
    }
}
