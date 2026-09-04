<?php

declare(strict_types=1);

namespace App\Application\UseCase\RegisterUser;

final class RegisterUserDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $plainPassword,
        public readonly string $pseudo,
        public readonly string $preferredLanguage,
    ) {
    }
}
