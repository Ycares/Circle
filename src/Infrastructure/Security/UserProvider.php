<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Exception\InvalidEmailAddressException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\EmailAddress;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $email = new EmailAddress($identifier);
        } catch (InvalidEmailAddressException) {
            throw new UserNotFoundException(\sprintf('Aucun utilisateur trouvé pour l\'identifiant "%s".', $identifier));
        }

        $user = $this->userRepository->ofEmail($email);

        if (null === $user) {
            throw new UserNotFoundException(\sprintf('Aucun utilisateur trouvé pour l\'identifiant "%s".', $identifier));
        }

        return new SecurityUser($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Les instances de "%s" ne sont pas supportées.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }
}
