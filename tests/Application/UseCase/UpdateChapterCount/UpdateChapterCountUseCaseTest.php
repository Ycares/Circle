<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\UpdateChapterCount;

use App\Application\UseCase\UpdateChapterCount\UpdateChapterCountUseCase;
use App\Domain\Entity\Book;
use App\Domain\Entity\Chapter;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Repository\ChapterRepositoryInterface;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use App\Tests\Support\SetsEntityId;
use PHPUnit\Framework\TestCase;

final class UpdateChapterCountUseCaseTest extends TestCase
{
    use SetsEntityId;

    private User $host;
    private ReadingClub $club;

    protected function setUp(): void
    {
        $this->host = new User(new EmailAddress('host@example.com'), 'hash', 'Host', 'fr', new \DateTimeImmutable());
        $this->setEntityId($this->host, 1);

        $this->club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $this->host, new \DateTimeImmutable());
        $this->setEntityId($this->club, 10);

        $book = new Book('Titre', ['Auteur'], 'fr', null, 'gb-1', ['Roman'], new \DateTimeImmutable());
        $this->club->setCurrentBook($book, 10);
    }

    public function testReducingChapterCountRemovesExcessChaptersAndClampsMemberships(): void
    {
        $readingClubRepository = $this->createMock(ReadingClubRepositoryInterface::class);
        $chapterRepository = $this->createMock(ChapterRepositoryInterface::class);
        $clubMembershipRepository = $this->createMock(ClubMembershipRepositoryInterface::class);

        $chapters = [];
        for ($number = 1; $number <= 10; ++$number) {
            $chapters[] = new Chapter($this->club, $number);
        }

        $memberAtMax = new ClubMembership($this->host, $this->club, ClubRole::MEMBER, new \DateTimeImmutable());
        $memberAtMax->declareChapter(10);

        $memberBelowNewCount = new ClubMembership($this->host, $this->club, ClubRole::MEMBER, new \DateTimeImmutable());
        $memberBelowNewCount->declareChapter(5);

        $readingClubRepository->expects(self::once())->method('ofId')->with(10)->willReturn($this->club);
        $chapterRepository->method('ofReadingClub')->willReturn($chapters);
        $clubMembershipRepository->method('ofReadingClub')->willReturn([$memberAtMax, $memberBelowNewCount]);

        $removedChapterNumbers = [];
        $chapterRepository->expects(self::exactly(2))
            ->method('remove')
            ->with(self::callback(function (Chapter $chapter) use (&$removedChapterNumbers): bool {
                $removedChapterNumbers[] = $chapter->chapterNumber();

                return true;
            }));

        $clubMembershipRepository->expects(self::once())->method('save')->with($memberAtMax);

        $useCase = new UpdateChapterCountUseCase($readingClubRepository, $chapterRepository, $clubMembershipRepository);
        $useCase->execute(10, 1, 8);

        self::assertSame([9, 10], $removedChapterNumbers);
        self::assertSame(8, $this->club->chapterCount());
        self::assertSame(8, $memberAtMax->declaredChapter());
        self::assertSame(5, $memberBelowNewCount->declaredChapter());
    }

    public function testIncreasingChapterCountAddsMissingChapters(): void
    {
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $chapterRepository = $this->createMock(ChapterRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);

        $readingClubRepository->method('ofId')->willReturn($this->club);
        $chapterRepository->method('ofReadingClub')->willReturn([]);
        $clubMembershipRepository->method('ofReadingClub')->willReturn([]);

        $chapterRepository->expects(self::exactly(2))->method('save');
        $chapterRepository->expects(self::never())->method('remove');

        $useCase = new UpdateChapterCountUseCase($readingClubRepository, $chapterRepository, $clubMembershipRepository);
        $useCase->execute(10, 1, 12);

        self::assertSame(12, $this->club->chapterCount());
    }
}
