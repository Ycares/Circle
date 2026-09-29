<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller;

use App\Application\Port\PasswordHasherInterface;
use App\Domain\Entity\Book;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\Discussion;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\Report;
use App\Domain\Entity\User;
use App\Domain\Entity\UserBook;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\DiscussionRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\ReportRepositoryInterface;
use App\Domain\Repository\UserBookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\BookStatus;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileControllerTest extends WebTestCase
{
    public function testProfileShowsBasicStats(): void
    {
        $client = static::createClient();
        $user = $this->createUser('profile-'.uniqid().'@example.com');

        $this->createUserBook($user, BookStatus::READ);
        $this->createUserBook($user, BookStatus::TO_READ);

        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host);
        $this->joinClub($user, $club);

        $this->loginAs($client, $user);
        $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Livres lus : 1');
        self::assertSelectorTextContains('body', 'Clubs rejoints : 1');
    }

    public function testReportsPageOnlyShowsReportsForHostedClubs(): void
    {
        $client = static::createClient();
        $hostA = $this->createUser('hosta-'.uniqid().'@example.com');
        $clubA = $this->createClub($hostA);
        $discussionA = $this->createDiscussion($clubA, $hostA, 'Message du club A');
        $this->createReport($hostA, 'discussion', $discussionA->id());

        $hostB = $this->createUser('hostb-'.uniqid().'@example.com');
        $clubB = $this->createClub($hostB);
        $discussionB = $this->createDiscussion($clubB, $hostB, 'Message du club B');
        $this->createReport($hostB, 'discussion', $discussionB->id());

        $this->loginAs($client, $hostA);
        $client->request('GET', '/profile/reports');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Message du club A');
        self::assertStringNotContainsString('Message du club B', (string) $client->getResponse()->getContent());
    }

    private function loginAs(KernelBrowser $client, User $user): void
    {
        $client->loginUser(new SecurityUser($user));
    }

    private function createUser(string $email): User
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepositoryInterface::class);
        $passwordHasher = $container->get(PasswordHasherInterface::class);

        $user = new User(
            new EmailAddress($email),
            $passwordHasher->hash('a-very-secure-password'),
            'ProfileTestUser',
            'fr',
            new \DateTimeImmutable(),
        );

        $userRepository->save($user);

        return $user;
    }

    private function createUserBook(User $user, BookStatus $status): UserBook
    {
        $container = static::getContainer();
        $bookRepository = $container->get(BookRepositoryInterface::class);
        $userBookRepository = $container->get(UserBookRepositoryInterface::class);

        $book = new Book('Livre '.uniqid(), ['Auteur'], 'fr', null, 'ext-'.uniqid(), ['Genre'], new \DateTimeImmutable());
        $bookRepository->save($book);

        $userBook = new UserBook($user, $book, $status, null, 0, new \DateTimeImmutable(), new \DateTimeImmutable());
        $userBookRepository->save($userBook);

        return $userBook;
    }

    private function createClub(User $host): ReadingClub
    {
        $container = static::getContainer();
        $readingClubRepository = $container->get(ReadingClubRepositoryInterface::class);
        $membershipRepository = $container->get(ClubMembershipRepositoryInterface::class);

        $club = new ReadingClub('Club '.uniqid(), 'Description', ClubVisibility::PUBLIC, $host, new \DateTimeImmutable());
        $readingClubRepository->save($club);
        $membershipRepository->save(new ClubMembership($host, $club, ClubRole::HOST, new \DateTimeImmutable()));

        return $club;
    }

    private function joinClub(User $user, ReadingClub $club): void
    {
        static::getContainer()->get(ClubMembershipRepositoryInterface::class)
            ->save(new ClubMembership($user, $club, ClubRole::MEMBER, new \DateTimeImmutable()));
    }

    private function createDiscussion(ReadingClub $club, User $author, string $text): Discussion
    {
        $discussionRepository = static::getContainer()->get(DiscussionRepositoryInterface::class);
        $discussion = new Discussion($club, null, $author, $text, new \DateTimeImmutable());
        $discussionRepository->save($discussion);

        return $discussion;
    }

    private function createReport(User $reporter, string $contentType, int $contentId): Report
    {
        $reportRepository = static::getContainer()->get(ReportRepositoryInterface::class);
        $report = new Report($contentType, $contentId, $reporter, 'Raison', new \DateTimeImmutable());
        $reportRepository->save($report);

        return $report;
    }
}
