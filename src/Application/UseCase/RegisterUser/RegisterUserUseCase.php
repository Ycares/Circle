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

    public function execute(RegisterUserDTO $dto): void
    {
        $email = new EmailAddress($dto->email);

        if (null !== $this->userRepository->ofEmail($email)) {
            throw new EmailAlreadyRegisteredException($email);
        }

        $user = new User(
            $email,
            $this->passwordHasher->hash($dto->plainPassword),
            $dto->pseudo,
            $dto->preferredLanguage,
            new \DateTimeImmutable(),
        );

        $this->userRepository->save($user);
    }
}
