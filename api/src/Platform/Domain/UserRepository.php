<?php

declare(strict_types=1);

namespace App\Platform\Domain;

interface UserRepository
{
    public function find(UserId $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;
}
