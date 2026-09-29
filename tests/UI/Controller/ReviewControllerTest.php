<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller;

use App\Application\Port\PasswordHasherInterface;
use App\Domain\Entity\Book;
use App\Domain\Entity\User;
use App\Domain\Entity\UserBook;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserBookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\BookStatus;
use App\Domain\ValueObject\EmailAddress;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ReviewControllerTest extends WebTestCase
{
    public function testPostingNonSpoilerReviewIsDirectlyVisible(): void
    {
        $client = static::createClient();
        $user = $this->createUser('reviewer-'.uniqid().'@example.com');
        $userBook = $this->createUserBook($user);
        $this->loginAs($client, $user);

        $crawler = $client->request('GET', '/library');
        $token = $this->extractToken($crawler, 'form[action$="/review"] input[name="_token"]');

        $client->request('POST', \sprintf('/library/books/%d/review', $userBook->id()), [
            'text' => 'Un excellent livre, sans aucun spoiler.',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/library');
        $crawler = $client->request('GET', '/library');

        self::assertSelectorTextContains('.review', 'Un excellent livre, sans aucun spoiler.');
        self::assertCount(0, $crawler->filter('.review p[hidden]'));
    }

    public function testPostingSpoilerReviewIsHiddenByDefault(): void
    {
        $client = static::createClient();
        $user = $this->createUser('reviewer-'.uniqid().'@example.com');
        $userBook = $this->createUserBook($user);
        $this->loginAs($client, $user);

        $crawler = $client->request('GET', '/library');
        $token = $this->extractToken($crawler, 'form[action$="/review"] input[name="_token"]');

        $client->request('POST', \sprintf('/library/books/%d/review', $userBook->id()), [
            'text' => 'Le majordome est le coupable.',
            'containsSpoiler' => '1',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/library');
        $crawler = $client->request('GET', '/library');

        self::assertCount(1, $crawler->filter('.review p[hidden]'));
        self::assertSelectorTextContains('.review p[hidden]', 'Le majordome est le coupable.');
    }

    private function loginAs(KernelBrowser $client, User $user): void
    {
        $client->loginUser(new SecurityUser($user));
    }

    private function extractToken(Crawler $crawler, string $selector): string
    {
        return (string) $crawler->filter($selector)->first()->attr('value');
    }

    private function createUser(string $email): User
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepositoryInterface::class);
        $passwordHasher = $container->get(PasswordHasherInterface::class);

        $user = new User(
            new EmailAddress($email),
            $passwordHasher->hash('a-very-secure-password'),
            'ReviewTestUser',
            'fr',
            new \DateTimeImmutable(),
        );

        $userRepository->save($user);

        return $user;
    }

    private function createUserBook(User $user): UserBook
    {
        $container = static::getContainer();
        $bookRepository = $container->get(BookRepositoryInterface::class);
        $userBookRepository = $container->get(UserBookRepositoryInterface::class);

        $book = new Book('Le Mystère', ['Agatha Christie'], 'fr', null, 'ext-'.uniqid(), ['Policier'], new \DateTimeImmutable());
        $bookRepository->save($book);

        $userBook = new UserBook($user, $book, BookStatus::READ, null, 100, new \DateTimeImmutable(), new \DateTimeImmutable());
        $userBookRepository->save($userBook);

        return $userBook;
    }
}
