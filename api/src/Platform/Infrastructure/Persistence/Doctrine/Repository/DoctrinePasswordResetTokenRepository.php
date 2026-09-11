<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Repository;

use App\Platform\Domain\PasswordResetToken;
use App\Platform\Domain\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrinePasswordResetTokenRepository implements PasswordResetTokenRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->entityManager->getRepository(PasswordResetToken::class)->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function save(PasswordResetToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }
}
