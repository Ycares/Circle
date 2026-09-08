<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\PostDiscussionMessage;

use App\Application\UseCase\PostDiscussionMessage\PostDiscussionMessageUseCase;
use App\Domain\Entity\Chapter;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\Discussion;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\ChapterRepositoryInterface;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\DiscussionRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use App\Tests\Support\SetsEntityId;
use PHPUnit\Framework\TestCase;

final class PostDiscussionMessageUseCaseTest extends TestCase
{
    use SetsEntityId;

    private User $author;
    private ReadingClub $club;
    private ClubMembership $membership;

    protected function setUp(): void
    {
        $this->author = new User(new EmailAddress('author@example.com'), 'hash', 'Author', 'fr', new \DateTimeImmutable());
        $this->setEntityId($this->author, 1);

        $this->club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $this->author, new \DateTimeImmutable());
        $this->setEntityId($this->club, 10);

        $this->membership = new ClubMembership($this->author, $this->club, ClubRole::MEMBER, new \DateTimeImmutable());
    }

    public function testPostsGlobalMessageWhenNoChapterGiven(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $chapterRepository = $this->createStub(ChapterRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);
        $discussionRepository = $this->createMock(DiscussionRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn($this->author);
        $readingClubRepository->method('ofId')->willReturn($this->club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn($this->membership);

        $savedDiscussion = null;
        $discussionRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Discussion $discussion) use (&$savedDiscussion): bool {
                $savedDiscussion = $discussion;

                return true;
            }));

        $useCase = new PostDiscussionMessageUseCase($userRepository, $readingClubRepository, $chapterRepository, $clubMembershipRepository, $discussionRepository);
        $useCase->execute(1, 10, null, 'Bienvenue à tous');

        self::assertNull($savedDiscussion->chapter());
    }

    public function testPostsMessageLinkedToAChapterOfTheSameClub(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $chapterRepository = $this->createMock(ChapterRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);
        $discussionRepository = $this->createMock(DiscussionRepositoryInterface::class);

        $chapter = new Chapter($this->club, 3);

        $userRepository->method('ofId')->willReturn($this->author);
        $readingClubRepository->method('ofId')->willReturn($this->club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn($this->membership);
        $chapterRepository->expects(self::once())->method('ofId')->with(30)->willReturn($chapter);

        $discussionRepository->expects(self::once())->method('save');

        $useCase = new PostDiscussionMessageUseCase($userRepository, $readingClubRepository, $chapterRepository, $clubMembershipRepository, $discussionRepository);
        $useCase->execute(1, 10, 30, 'Spoiler du chapitre 3');
    }

    public function testThrowsWhenChapterBelongsToAnotherClub(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $chapterRepository = $this->createStub(ChapterRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);
        $discussionRepository = $this->createMock(DiscussionRepositoryInterface::class);

        $otherHost = new User(new EmailAddress('other@example.com'), 'hash', 'Other', 'fr', new \DateTimeImmutable());
        $otherClub = new ReadingClub('Autre club', 'Description', ClubVisibility::PUBLIC, $otherHost, new \DateTimeImmutable());
        $this->setEntityId($otherClub, 20);
        $chapterFromOtherClub = new Chapter($otherClub, 1);

        $userRepository->method('ofId')->willReturn($this->author);
        $readingClubRepository->method('ofId')->willReturn($this->club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn($this->membership);
        $chapterRepository->method('ofId')->willReturn($chapterFromOtherClub);
        $discussionRepository->expects(self::never())->method('save');

        $this->expectException(EntityNotFoundException::class);

        $useCase = new PostDiscussionMessageUseCase($userRepository, $readingClubRepository, $chapterRepository, $clubMembershipRepository, $discussionRepository);
        $useCase->execute(1, 10, 1, 'Message');
    }

    public function testThrowsWhenAuthorIsNotAMember(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $chapterRepository = $this->createStub(ChapterRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);
        $discussionRepository = $this->createMock(DiscussionRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn($this->author);
        $readingClubRepository->method('ofId')->willReturn($this->club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn(null);
        $discussionRepository->expects(self::never())->method('save');

        $this->expectException(UnauthorizedActionException::class);

        $useCase = new PostDiscussionMessageUseCase($userRepository, $readingClubRepository, $chapterRepository, $clubMembershipRepository, $discussionRepository);
        $useCase->execute(1, 10, null, 'Message');
    }
}
