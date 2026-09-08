<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\ListVisibleDiscussions;

use App\Application\UseCase\ListVisibleDiscussions\ListVisibleDiscussionsUseCase;
use App\Domain\Entity\Chapter;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\Discussion;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\DiscussionRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\SpoilerGuardService;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class ListVisibleDiscussionsUseCaseTest extends TestCase
{
    public function testOnlyReturnsDiscussionsUpToDeclaredChapterPlusGlobalChannel(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);
        $discussionRepository = $this->createStub(DiscussionRepositoryInterface::class);

        $host = new User(new EmailAddress('host@example.com'), 'hash', 'Host', 'fr', new \DateTimeImmutable());
        $club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $host, new \DateTimeImmutable());
        $club->setChapterCount(5);

        $membership = new ClubMembership($host, $club, ClubRole::MEMBER, new \DateTimeImmutable());
        $membership->declareChapter(2);

        $chapters = [];
        for ($number = 1; $number <= 5; ++$number) {
            $chapters[$number] = new Chapter($club, $number);
        }

        $globalDiscussion = new Discussion($club, null, $host, 'Bienvenue', new \DateTimeImmutable());
        $chapter1Discussion = new Discussion($club, $chapters[1], $host, 'Spoiler chapitre 1', new \DateTimeImmutable());
        $chapter2Discussion = new Discussion($club, $chapters[2], $host, 'Spoiler chapitre 2', new \DateTimeImmutable());
        $chapter3Discussion = new Discussion($club, $chapters[3], $host, 'Spoiler chapitre 3', new \DateTimeImmutable());
        $chapter5Discussion = new Discussion($club, $chapters[5], $host, 'Spoiler chapitre 5', new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($host);
        $readingClubRepository->method('ofId')->willReturn($club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn($membership);
        $discussionRepository->method('ofReadingClub')->willReturn([
            $globalDiscussion,
            $chapter1Discussion,
            $chapter2Discussion,
            $chapter3Discussion,
            $chapter5Discussion,
        ]);

        $useCase = new ListVisibleDiscussionsUseCase(
            $userRepository,
            $readingClubRepository,
            $clubMembershipRepository,
            $discussionRepository,
            new SpoilerGuardService(),
        );

        $visible = $useCase->execute(1, 10);

        self::assertCount(3, $visible);
        self::assertContains($globalDiscussion, $visible);
        self::assertContains($chapter1Discussion, $visible);
        self::assertContains($chapter2Discussion, $visible);
        self::assertNotContains($chapter3Discussion, $visible);
        self::assertNotContains($chapter5Discussion, $visible);
    }

    public function testThrowsWhenRequesterIsNotAMember(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);
        $discussionRepository = $this->createStub(DiscussionRepositoryInterface::class);

        $host = new User(new EmailAddress('host@example.com'), 'hash', 'Host', 'fr', new \DateTimeImmutable());
        $club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $host, new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($host);
        $readingClubRepository->method('ofId')->willReturn($club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $useCase = new ListVisibleDiscussionsUseCase(
            $userRepository,
            $readingClubRepository,
            $clubMembershipRepository,
            $discussionRepository,
            new SpoilerGuardService(),
        );

        $useCase->execute(1, 10);
    }
}
