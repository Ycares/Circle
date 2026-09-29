<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Domain\Entity\User;
use App\Infrastructure\Security\SecurityUser;

trait CurrentUserTrait
{
    private function currentUser(): User
    {
        $securityUser = $this->getUser();
        \assert($securityUser instanceof SecurityUser);

        return $securityUser->user();
    }
}
