<?php

declare(strict_types=1);

namespace App\Application\UseCase\RegisterUser;

use App\Application\Port\PasswordHasherInterface;
use App\Domain\Entity\User;
use App\Domain\Exception\EmailAlreadyRegisteredException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\EmailAddress;

final class RegisterUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function execute(string $email, string $plainPassword, string $pseudo, string $preferredLanguage): void
    {
        $emailAddress = new EmailAddress($email);

        if (null !== $this->userRepository->ofEmail($emailAddress)) {
            throw new EmailAlreadyRegisteredException($emailAddress);
        }

        $user = new User(
            $emailAddress,
            $this->passwordHasher->hash($plainPassword),
            $pseudo,
            $preferredLanguage,
            new \DateTimeImmutable(),
        );

        $this->userRepository->save($user);
    }
}
