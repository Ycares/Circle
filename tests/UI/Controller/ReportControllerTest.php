<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller;

use App\Application\Port\PasswordHasherInterface;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\Discussion;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\DiscussionRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\ReportRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ReportControllerTest extends WebTestCase
{
    public function testReportingDiscussionPersistsReport(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC);
        $discussion = $this->createDiscussion($club, $host, 'Un message quelconque.');

        $reporter = $this->createUser('reporter-'.uniqid().'@example.com');
        $this->joinClub($reporter, $club);
        $this->loginAs($client, $reporter);

        $crawler = $client->request('GET', \sprintf('/clubs/%d/discussions', $club->id()));
        $token = $this->extractToken($crawler, 'form[action$="/reports"] input[name="_token"]');

        $client->request('POST', '/reports', [
            'contentType' => 'discussion',
            'contentId' => (string) $discussion->id(),
            'reason' => 'Contenu inapproprié',
            'redirectTo' => \sprintf('/clubs/%d/discussions', $club->id()),
            '_token' => $token,
        ]);

        self::assertResponseRedirects(\sprintf('/clubs/%d/discussions', $club->id()));

        $reportRepository = static::getContainer()->get(ReportRepositoryInterface::class);
        $reports = array_filter(
            $reportRepository->all(),
            static fn ($report): bool => 'discussion' === $report->reportedContentType() && $report->reportedContentId() === $discussion->id(),
        );

        self::assertCount(1, $reports);
        self::assertSame('Contenu inapproprié', current($reports)->reason());
    }

    public function testReportingWithInvalidContentTypeIsRejectedGracefully(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC);
        $this->createDiscussion($club, $host, 'Un message quelconque.');

        $reporter = $this->createUser('reporter-'.uniqid().'@example.com');
        $this->joinClub($reporter, $club);
        $this->loginAs($client, $reporter);

        $crawler = $client->request('GET', \sprintf('/clubs/%d/discussions', $club->id()));
        $token = $this->extractToken($crawler, 'form[action$="/reports"] input[name="_token"]');

        $client->request('POST', '/reports', [
            'contentType' => 'not-a-valid-type',
            'contentId' => '1',
            'reason' => 'Peu importe',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/profile');
    }

    public function testRedirectToExternalUrlFallsBackToProfile(): void
    {
        $client = static::createClient();
        $host = $this->createUser('host-'.uniqid().'@example.com');
        $club = $this->createClub($host, ClubVisibility::PUBLIC);
        $discussion = $this->createDiscussion($club, $host, 'Un message quelconque.');

        $reporter = $this->createUser('reporter-'.uniqid().'@example.com');
        $this->joinClub($reporter, $club);
        $this->loginAs($client, $reporter);

        $crawler = $client->request('GET', \sprintf('/clubs/%d/discussions', $club->id()));
        $token = $this->extractToken($crawler, 'form[action$="/reports"] input[name="_token"]');

        $client->request('POST', '/reports', [
            'contentType' => 'discussion',
            'contentId' => (string) $discussion->id(),
            'reason' => 'Contenu inapproprié',
            'redirectTo' => '//evil.example.com/phishing',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/profile');
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
            'ReportTestUser',
            'fr',
            new \DateTimeImmutable(),
        );

        $userRepository->save($user);

        return $user;
    }

    private function createClub(User $host, ClubVisibility $visibility): ReadingClub
    {
        $container = static::getContainer();
        $readingClubRepository = $container->get(ReadingClubRepositoryInterface::class);
        $membershipRepository = $container->get(ClubMembershipRepositoryInterface::class);

        $club = new ReadingClub('Club '.uniqid(), 'Description', $visibility, $host, new \DateTimeImmutable());
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
        $container = static::getContainer();
        $discussionRepository = $container->get(DiscussionRepositoryInterface::class);

        $discussion = new Discussion($club, null, $author, $text, new \DateTimeImmutable());
        $discussionRepository->save($discussion);

        return $discussion;
    }
}
