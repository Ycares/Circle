<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller\Auth;

use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\EmailAddress;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegisterControllerTest extends WebTestCase
{
    public function testRegisterFormIsDisplayed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/register"]');
    }

    public function testSubmittingValidDataCreatesAccountAndRedirectsToLogin(): void
    {
        $client = static::createClient();
        $email = \sprintf('new-user-%s@example.com', uniqid());

        $crawler = $client->request('GET', '/register');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/register', [
            'email' => $email,
            'pseudo' => 'NewUser',
            'password' => 'a-very-secure-password',
            'preferredLanguage' => 'fr',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/login');

        $userRepository = static::getContainer()->get(UserRepositoryInterface::class);
        $user = $userRepository->ofEmail(new EmailAddress($email));

        self::assertNotNull($user);
        self::assertSame('NewUser', $user->pseudo());
        self::assertNotSame('a-very-secure-password', $user->passwordHash());
    }

    public function testSubmittingAlreadyUsedEmailShowsError(): void
    {
        $client = static::createClient();
        $email = \sprintf('duplicate-%s@example.com', uniqid());

        $crawler = $client->request('GET', '/register');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/register', [
            'email' => $email,
            'pseudo' => 'FirstUser',
            'password' => 'a-very-secure-password',
            'preferredLanguage' => 'fr',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/login');

        $crawler = $client->request('GET', '/register');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/register', [
            'email' => $email,
            'pseudo' => 'SecondUser',
            'password' => 'another-password',
            'preferredLanguage' => 'fr',
            '_token' => $token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'déjà');
    }

    public function testSubmittingInvalidEmailFormatShowsError(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/register', [
            'email' => 'not-an-email',
            'pseudo' => 'InvalidEmailUser',
            'password' => 'a-very-secure-password',
            'preferredLanguage' => 'fr',
            '_token' => $token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'valide');
    }
}
