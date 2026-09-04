<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller\Auth;

use App\Application\Port\PasswordHasherInterface;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\EmailAddress;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    public function testLoginFormIsDisplayed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/login"]');
    }

    public function testSubmittingValidCredentialsLogsUserIn(): void
    {
        $client = static::createClient();
        $email = \sprintf('login-%s@example.com', uniqid());
        $plainPassword = 'a-very-secure-password';

        $this->createUser($email, $plainPassword);

        $crawler = $client->request('GET', '/login');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => $plainPassword,
            '_csrf_token' => $token,
        ]);

        // Pas de route d'accueil dans cette branche : on vérifie l'authentification
        // (redirection hors de /login), sans suivre la redirection.
        self::assertResponseRedirects();
        self::assertNotSame('/login', $client->getResponse()->headers->get('Location'));
    }

    public function testSubmittingWrongPasswordShowsError(): void
    {
        $client = static::createClient();
        $email = \sprintf('login-wrong-%s@example.com', uniqid());

        $this->createUser($email, 'a-very-secure-password');

        $crawler = $client->request('GET', '/login');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => 'wrong-password',
            '_csrf_token' => $token,
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'incorrect');
    }

    private function createUser(string $email, string $plainPassword): void
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepositoryInterface::class);
        $passwordHasher = $container->get(PasswordHasherInterface::class);

        $userRepository->save(new User(
            new EmailAddress($email),
            $passwordHasher->hash($plainPassword),
            'LoginTestUser',
            'fr',
            new \DateTimeImmutable(),
        ));
    }
}
