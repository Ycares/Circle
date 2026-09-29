<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller;

use App\Application\Port\BookDetailsDTO;
use App\Application\Port\BookMetadataProviderInterface;
use App\Application\Port\PasswordHasherInterface;
use App\Domain\Entity\Book;
use App\Domain\Entity\Chapter;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\ChapterRepositoryInterface;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use App\Infrastructure\BookMetadata\GoogleBooksProvider;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ClubControllerTest extends WebTestCase
{
    public function testClubsIndexRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/clubs');

        self::assertResponseRedirects('/login');
    }

    public function testCreatingClubMakesHostAMember(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $this->loginAs($client, $host);

        $clubName = 'Club de test '.uniqid();

        $crawler = $client->request('GET', '/clubs');
        $token = $this->extractToken($crawler, 'form[action$="/clubs"] input[name="_token"]');

        $client->request('POST', '/clubs', [
            'name' => $clubName,
            'description' => 'Un club pour les tests',
            'visibility' => 'public',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/clubs');
        $client->followRedirect();
        self::assertSelectorTextContains('body', $clubName);

        $readingClubRepository = static::getContainer()->get(ReadingClubRepositoryInterface::class);
        $clubs = $readingClubRepository->all();
        $club = current(array_filter($clubs, static fn (ReadingClub $c): bool => $clubName === $c->name()));

        self::assertNotFalse($club);

        $membershipRepository = static::getContainer()->get(ClubMembershipRepositoryInterface::class);
        $membership = $membershipRepository->ofUserAndClub($host, $club);

        self::assertNotNull($membership);
        self::assertSame(ClubRole::HOST, $membership->role());
    }

    public function testJoiningPublicClubSucceeds(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC);

        $joiner = $this->createUser('joiner-'.uniqid().'@example.com');
        $this->loginAs($client, $joiner);

        $crawler = $client->request('GET', \sprintf('/clubs/%d', $club->id()));
        $token = $this->extractToken($crawler, 'form[action$="/join"] input[name="_token"]');

        $client->request('POST', \sprintf('/clubs/%d/join', $club->id()), ['_token' => $token]);

        self::assertResponseRedirects(\sprintf('/clubs/%d', $club->id()));

        $membershipRepository = static::getContainer()->get(ClubMembershipRepositoryInterface::class);
        self::assertNotNull($membershipRepository->ofUserAndClub($joiner, $club));
    }

    public function testJoiningPrivateClubIsRejected(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $privateClub = $this->createClub($host, ClubVisibility::PRIVATE_INVITE);

        $joiner = $this->createUser('joiner-'.uniqid().'@example.com');
        $this->loginAs($client, $joiner);

        // Le formulaire "rejoindre" n'est pas rendu pour un club privé : on récupère un
        // jeton CSRF valide pour l'id 'club_join' (lié à la session du joiner, pas à un
        // club en particulier) via un club public bidon.
        $decoyClub = $this->createClub($host, ClubVisibility::PUBLIC);
        $crawler = $client->request('GET', \sprintf('/clubs/%d', $decoyClub->id()));
        $token = $this->extractToken($crawler, 'form[action$="/join"] input[name="_token"]');

        $client->request('POST', \sprintf('/clubs/%d/join', $privateClub->id()), ['_token' => $token]);

        self::assertResponseRedirects(\sprintf('/clubs/%d', $privateClub->id()));

        $membershipRepository = static::getContainer()->get(ClubMembershipRepositoryInterface::class);
        self::assertNull($membershipRepository->ofUserAndClub($joiner, $privateClub));
    }

    public function testHostSettingBookGeneratesChapters(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        static::getContainer()->set(GoogleBooksProvider::class, $this->fakeProvider());

        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC);
        $this->loginAs($client, $host);

        [, $setBookToken] = $this->harvestBookTokens($client, $club->id());

        $client->request('POST', \sprintf('/clubs/%d/book', $club->id()), [
            'externalId' => 'abc123',
            'chapterCount' => '5',
            '_token' => $setBookToken,
        ]);

        self::assertResponseRedirects(\sprintf('/clubs/%d', $club->id()));

        $chapterRepository = static::getContainer()->get(ChapterRepositoryInterface::class);
        $readingClubRepository = static::getContainer()->get(ReadingClubRepositoryInterface::class);
        $updatedClub = $readingClubRepository->ofId($club->id());

        self::assertNotNull($updatedClub);
        self::assertSame(5, $updatedClub->chapterCount());
        self::assertCount(5, $chapterRepository->ofReadingClub($updatedClub));
    }

    public function testNonHostCannotSetBook(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        static::getContainer()->set(GoogleBooksProvider::class, $this->fakeProvider());

        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC);

        $intruder = $this->createUser('intruder-'.uniqid().'@example.com');
        $this->joinClub($intruder, $club, ClubRole::MEMBER);
        $this->loginAs($client, $intruder);

        // L'intrus n'est pas hôte de $club, donc le formulaire n'y est jamais rendu :
        // on récupère des jetons valides pour sa session via un club bidon dont il est
        // lui-même l'hôte.
        $decoyClub = $this->createClub($intruder, ClubVisibility::PUBLIC);
        [, $setBookToken] = $this->harvestBookTokens($client, $decoyClub->id());

        $client->request('POST', \sprintf('/clubs/%d/book', $club->id()), [
            'externalId' => 'abc123',
            'chapterCount' => '5',
            '_token' => $setBookToken,
        ]);

        self::assertResponseRedirects(\sprintf('/clubs/%d', $club->id()));

        $readingClubRepository = static::getContainer()->get(ReadingClubRepositoryInterface::class);
        $untouchedClub = $readingClubRepository->ofId($club->id());

        self::assertNotNull($untouchedClub);
        self::assertNull($untouchedClub->currentBook());
    }

    public function testDeclaringProgressUpdatesMembership(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC, chapterCount: 3);
        $this->loginAs($client, $host);

        $crawler = $client->request('GET', \sprintf('/clubs/%d', $club->id()));
        $token = (string) $crawler->filter('[data-chapter-progress-token-value]')->first()->attr('data-chapter-progress-token-value');

        $client->request('PATCH', \sprintf('/clubs/%d/progress', $club->id()), [
            'declaredChapter' => '2',
            '_token' => $token,
        ]);

        self::assertResponseRedirects(\sprintf('/clubs/%d', $club->id()));

        $membershipRepository = static::getContainer()->get(ClubMembershipRepositoryInterface::class);
        $membership = $membershipRepository->ofUserAndClub($host, $club);

        self::assertNotNull($membership);
        self::assertSame(2, $membership->declaredChapter());
    }

    public function testDiscussionForUnreachedChapterIsNeverSentToNonAllowedMember(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC, chapterCount: 3);

        $member = $this->createUser('member-'.uniqid().'@example.com');
        $membership = $this->joinClub($member, $club, ClubRole::MEMBER);
        $membership->declareChapter(1);
        static::getContainer()->get(ClubMembershipRepositoryInterface::class)->save($membership);

        $chapterRepository = static::getContainer()->get(ChapterRepositoryInterface::class);
        $chapters = $chapterRepository->ofReadingClub($club);
        $chapterTwo = current(array_filter($chapters, static fn (Chapter $c): bool => 2 === $c->chapterNumber()));
        self::assertNotFalse($chapterTwo);

        // L'hôte poste un message spoiler dans le chapitre 2, que le membre n'a pas
        // encore atteint (declaredChapter = 1).
        $this->loginAs($client, $host);
        $crawler = $client->request('GET', \sprintf('/clubs/%d/discussions', $club->id()));
        $postToken = $this->extractToken($crawler, 'form[action$="/discussions"] input[name="_token"]');

        $client->request('POST', \sprintf('/clubs/%d/discussions', $club->id()), [
            'chapterId' => (string) $chapterTwo->id(),
            'text' => 'LE TEXTE SPOILER SECRET',
            '_token' => $postToken,
        ]);

        $this->loginAs($client, $member);
        $client->request('GET', \sprintf('/clubs/%d/discussions?chapter=%d', $club->id(), $chapterTwo->id()));

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('LE TEXTE SPOILER SECRET', (string) $client->getResponse()->getContent());
    }

    public function testPostingDiscussionRequiresMembership(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC);

        $stranger = $this->createUser('stranger-'.uniqid().'@example.com');
        $this->loginAs($client, $stranger);

        // Le formulaire n'est pas rendu pour un non-membre : on récupère un jeton valide
        // pour la session du stranger via un club bidon dont il est lui-même membre.
        $decoyClub = $this->createClub($host, ClubVisibility::PUBLIC);
        $this->joinClub($stranger, $decoyClub, ClubRole::MEMBER);
        $crawler = $client->request('GET', \sprintf('/clubs/%d/discussions', $decoyClub->id()));
        $token = $this->extractToken($crawler, 'form[action$="/discussions"] input[name="_token"]');

        $client->request('POST', \sprintf('/clubs/%d/discussions', $club->id()), [
            'chapterId' => '',
            'text' => 'Bonjour',
            '_token' => $token,
        ]);

        self::assertResponseRedirects();

        $client->request('GET', \sprintf('/clubs/%d/discussions', $club->id()));
        self::assertStringNotContainsString('Bonjour', (string) $client->getResponse()->getContent());
    }

    /**
     * @return array{0: string, 1: string} le jeton de recherche puis celui de sélection du livre
     */
    private function harvestBookTokens(KernelBrowser $client, int $clubId): array
    {
        $crawler = $client->request('GET', \sprintf('/clubs/%d', $clubId));
        $searchToken = $this->extractToken($crawler, 'form[action$="/book-search"] input[name="_token"]');

        $results = $client->request('POST', \sprintf('/clubs/%d/book-search', $clubId), ['q' => 'Fondation', '_token' => $searchToken]);
        $setBookToken = $this->extractToken($results, 'form[action$="/book"] input[name="_token"]');

        return [$searchToken, $setBookToken];
    }

    private function fakeProvider(): BookMetadataProviderInterface
    {
        return new class implements BookMetadataProviderInterface {
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
        };
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
            'ClubTestUser',
            'fr',
            new \DateTimeImmutable(),
        );

        $userRepository->save($user);

        return $user;
    }

    private function createClub(User $host, ClubVisibility $visibility, ?int $chapterCount = null): ReadingClub
    {
        $container = static::getContainer();
        $readingClubRepository = $container->get(ReadingClubRepositoryInterface::class);
        $membershipRepository = $container->get(ClubMembershipRepositoryInterface::class);

        $club = new ReadingClub('Club '.uniqid(), 'Description', $visibility, $host, new \DateTimeImmutable());
        $readingClubRepository->save($club);

        $membershipRepository->save(new ClubMembership($host, $club, ClubRole::HOST, new \DateTimeImmutable()));

        if (null !== $chapterCount) {
            $chapterRepository = $container->get(ChapterRepositoryInterface::class);
            $bookRepository = $container->get(BookRepositoryInterface::class);

            $book = new Book('Fondation', ['Isaac Asimov'], 'fr', null, 'ext-'.uniqid(), ['Science-fiction'], new \DateTimeImmutable());
            $bookRepository->save($book);

            $club->setCurrentBook($book, $chapterCount);
            $readingClubRepository->save($club);

            for ($i = 1; $i <= $chapterCount; ++$i) {
                $chapterRepository->save(new Chapter($club, $i));
            }
        }

        return $club;
    }

    private function joinClub(User $user, ReadingClub $club, ClubRole $role): ClubMembership
    {
        $membershipRepository = static::getContainer()->get(ClubMembershipRepositoryInterface::class);
        $membership = new ClubMembership($user, $club, $role, new \DateTimeImmutable());
        $membershipRepository->save($membership);

        return $membership;
    }
}
