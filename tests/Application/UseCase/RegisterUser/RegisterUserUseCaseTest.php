<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\RegisterUser;

use App\Application\Port\PasswordHasherInterface;
use App\Application\UseCase\RegisterUser\RegisterUserUseCase;
use App\Domain\Entity\User;
use App\Domain\Exception\EmailAlreadyRegisteredException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class RegisterUserUseCaseTest extends TestCase
{
    public function testRegistersUserWithHashedPassword(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $userRepository->method('ofEmail')->willReturn(null);
        $passwordHasher->expects(self::once())->method('hash')->with('plain-password')->willReturn('hashed-password');

        $savedUser = null;
        $userRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (User $user) use (&$savedUser): bool {
                $savedUser = $user;

                return true;
            }));

        $useCase = new RegisterUserUseCase($userRepository, $passwordHasher);
        $useCase->execute('user@example.com', 'plain-password', 'Pseudo', 'fr');

        self::assertInstanceOf(User::class, $savedUser);
        self::assertSame('user@example.com', $savedUser->email()->value());
        self::assertSame('hashed-password', $savedUser->passwordHash());
        self::assertSame('Pseudo', $savedUser->pseudo());
    }

    public function testThrowsWhenEmailAlreadyRegistered(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createStub(PasswordHasherInterface::class);

        $existingUser = new User(new EmailAddress('user@example.com'), 'hash', 'Pseudo', 'fr', new \DateTimeImmutable());
        $userRepository->method('ofEmail')->willReturn($existingUser);
        $userRepository->expects(self::never())->method('save');

        $this->expectException(EmailAlreadyRegisteredException::class);

        $useCase = new RegisterUserUseCase($userRepository, $passwordHasher);
        $useCase->execute('user@example.com', 'plain-password', 'Pseudo', 'fr');
    }
}
