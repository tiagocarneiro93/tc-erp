<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-1.md task 1.6: a tree via parent_id, cycle-free.
 */
final class ProductFamiliesControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/product-families');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreatingAndListingAFamilyTree(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $rootId = $this->createFamily($client, $companyId, 'Bebidas');
        $childId = $this->createFamily($client, $companyId, 'Vinhos', $rootId);

        $client->request('GET', "/api/v1/companies/{$companyId}/product-families", server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{id: string, name: string, parent_id: ?string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['items']);

        $byId = [];
        foreach ($body['items'] as $item) {
            $byId[$item['id']] = $item;
        }
        self::assertSame('Bebidas', $byId[$rootId]['name']);
        self::assertNull($byId[$rootId]['parent_id']);
        self::assertSame('Vinhos', $byId[$childId]['name']);
        self::assertSame($rootId, $byId[$childId]['parent_id']);
    }

    public function testUpdatingAFamilyToBeItsOwnDescendantsChildIsRejectedAsACycle(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $rootId = $this->createFamily($client, $companyId, 'Bebidas');
        $childId = $this->createFamily($client, $companyId, 'Vinhos', $rootId);

        // Reparenting the root under its own child would make the tree cyclic.
        $client->request('PUT', "/api/v1/companies/{$companyId}/product-families/{$rootId}", server: self::HEADERS, content: json_encode([
            'name' => 'Bebidas',
            'parent_id' => $childId,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdatingAFamilyToBeItsOwnParentIsRejectedAsACycle(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $familyId = $this->createFamily($client, $companyId, 'Bebidas');

        $client->request('PUT', "/api/v1/companies/{$companyId}/product-families/{$familyId}", server: self::HEADERS, content: json_encode([
            'name' => 'Bebidas',
            'parent_id' => $familyId,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingWithAnUnknownParentIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/product-families", server: self::HEADERS, content: json_encode([
            'name' => 'Vinhos',
            'parent_id' => '00000000-0000-7000-8000-000000000000',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createFamily(KernelBrowser $client, string $companyId, string $name, ?string $parentId = null): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/product-families", server: self::HEADERS, content: json_encode([
            'name' => $name,
            'parent_id' => $parentId,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created['id'];
    }

    private function createCompany(KernelBrowser $client, string $legalName = 'A Company Lda'): string
    {
        $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            'nif' => $this->uniqueNif(),
            'legal_name' => $legalName,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created['id'];
    }

    private function registerAndLogIn(KernelBrowser $client, string $email, string $password): UserId
    {
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Flow User', $hasher->hash($password), new \DateTimeImmutable());
        $user->changePassword($hasher->hash($password), new \DateTimeImmutable());
        $users->save($user);

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return $user->id();
    }

    private function uniqueEmail(): string
    {
        return \sprintf('product-families-%s@example.test', bin2hex(random_bytes(8)));
    }

    private function uniqueNif(): string
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

        return $nif;
    }
}
