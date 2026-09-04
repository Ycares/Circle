<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use App\Domain\ValueObject\EmailAddress;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function ofId(int $id): ?User;

    public function ofEmail(EmailAddress $email): ?User;
}
