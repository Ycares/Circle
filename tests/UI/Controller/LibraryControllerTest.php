<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller;

use App\Application\Port\BookDetailsDTO;
use App\Application\Port\BookMetadataProviderInterface;
use App\Application\Port\PasswordHasherInterface;
use App\Domain\Entity\Book;
use App\Domain\Entity\User;
use App\Domain\Entity\UserBook;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserBookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\BookStatus;
use App\Domain\ValueObject\EmailAddress;
use App\Infrastructure\BookMetadata\GoogleBooksProvider;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class LibraryControllerTest extends WebTestCase
{
    public function testLibraryRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library');

        self::assertResponseRedirects('/login');
    }

    public function testEmptyLibraryIsDisplayed(): void
    {
        $client = static::createClient();
        $this->loginAs($client, $this->createUser('empty-library-'.uniqid().'@example.com'));

        $client->request('GET', '/library');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'vide');
    }

    public function testSearchingBooksDisplaysResults(): void
    {
        $client = static::createClient();
        // Le client reboote le noyau (et son conteneur) avant chaque requête par défaut,
        // ce qui effacerait le remplacement de service ci-dessous entre les deux appels.
        $client->disableReboot();
        static::getContainer()->set(GoogleBooksProvider::class, new class implements BookMetadataProviderInterface {
            public function search(string $query): array
            {
                return [
                    ['externalId' => 'abc123', 'title' => 'Fondation', 'authors' => ['Isaac Asimov'], 'coverUrl' => null],
                ];
            }

            public function fetchById(string $externalId): ?BookDetailsDTO
            {
                return null;
            }
        });

        $this->loginAs($client, $this->createUser('search-'.uniqid().'@example.com'));

        $crawler = $client->request('GET', '/library');
        $token = $this->extractToken($crawler, 'form[action$="/library/search"] input[name="_token"]');

        $client->request('POST', '/library/search', ['q' => 'Fondation', '_token' => $token]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.search-result strong', 'Fondation');
    }

    public function testAddingBookFromSearchResultsAddsItToLibrary(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        static::getContainer()->set(GoogleBooksProvider::class, new class implements BookMetadataProviderInterface {
            public function search(string $query): array
            {
                return [
                    ['externalId' => 'abc123', 'title' => 'Fondation', 'authors' => ['Isaac Asimov'], 'coverUrl' => null],
                ];
            }

            public function fetchById(string $externalId): BookDetailsDTO
            {
                return new BookDetailsDTO($externalId, 'Fondation', ['Isaac Asimov'], 'fr', null, ['Science-fiction']);
            }
        });

        $this->loginAs($client, $this->createUser('add-book-'.uniqid().'@example.com'));

        $crawler = $client->request('GET', '/library');
        $searchToken = $this->extractToken($crawler, 'form[action$="/library/search"] input[name="_token"]');

        $searchResults = $client->request('POST', '/library/search', ['q' => 'Fondation', '_token' => $searchToken]);
        $addToken = $this->extractToken($searchResults, 'form[action$="/library/books"] input[name="_token"]');

        $client->request('POST', '/library/books', ['externalId' => 'abc123', '_token' => $addToken]);

        self::assertResponseRedirects('/library');
        $client->followRedirect();
        self::assertSelectorTextContains('.user-book strong', 'Fondation');
    }

    public function testUpdatingProgressOfOwnBookSucceeds(): void
    {
        $client = static::createClient();
        $user = $this->createUser('progress-'.uniqid().'@example.com');
        $userBook = $this->createUserBook($user);
        $this->loginAs($client, $user);

        $crawler = $client->request('GET', '/library');
        $token = $this->extractToken($crawler, 'form[action*="/progress"] input[name="_token"]');

        $client->request('PATCH', \sprintf('/library/books/%d/progress', $userBook->id()), [
            'progress' => '42',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/library');

        $userBookRepository = static::getContainer()->get(UserBookRepositoryInterface::class);
        $updated = $userBookRepository->ofId($userBook->id());

        self::assertNotNull($updated);
        self::assertSame(42, $updated->progress());
    }

    public function testUpdatingProgressOfAnotherUsersBookIsRejected(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner-'.uniqid().'@example.com');
        $userBook = $this->createUserBook($owner);

        $intruder = $this->createUser('intruder-'.uniqid().'@example.com');
        // Livre bidon, uniquement pour obtenir un jeton CSRF valide (lié à la session
        // de l'intrus, pas à un UserBook en particulier) depuis une page rendue.
        $this->createUserBook($intruder);
        $this->loginAs($client, $intruder);

        $crawler = $client->request('GET', '/library');
        $token = $this->extractToken($crawler, 'form[action*="/progress"] input[name="_token"]');

        $client->request('PATCH', \sprintf('/library/books/%d/progress', $userBook->id()), [
            'progress' => '99',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/library');

        $userBookRepository = static::getContainer()->get(UserBookRepositoryInterface::class);
        $untouched = $userBookRepository->ofId($userBook->id());

        self::assertNotNull($untouched);
        self::assertSame(0, $untouched->progress());
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
            'LibraryTestUser',
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

        $book = new Book('Fondation', ['Isaac Asimov'], 'fr', null, 'ext-'.uniqid(), ['Science-fiction'], new \DateTimeImmutable());
        $bookRepository->save($book);

        $userBook = new UserBook($user, $book, BookStatus::TO_READ, null, 0, new \DateTimeImmutable(), new \DateTimeImmutable());
        $userBookRepository->save($userBook);

        return $userBook;
    }
}
