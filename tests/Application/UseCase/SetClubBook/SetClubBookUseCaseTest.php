<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\SetClubBook;

use App\Application\UseCase\SetClubBook\SetClubBookUseCase;
use App\Domain\Entity\Chapter;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\ChapterRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use App\Tests\Support\SetsEntityId;
use PHPUnit\Framework\TestCase;

final class SetClubBookUseCaseTest extends TestCase
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
    }

    public function testReplacingBookRemovesOldChaptersAndGeneratesNewOnes(): void
    {
        $readingClubRepository = $this->createMock(ReadingClubRepositoryInterface::class);
        $bookRepository = $this->createMock(BookRepositoryInterface::class);
        $chapterRepository = $this->createMock(ChapterRepositoryInterface::class);

        // Le club a déjà 3 chapitres pour un livre A précédemment assigné.
        $oldChapters = [
            new Chapter($this->club, 1),
            new Chapter($this->club, 2),
            new Chapter($this->club, 3),
        ];

        $readingClubRepository->expects(self::once())->method('ofId')->with(10)->willReturn($this->club);
        $chapterRepository->expects(self::once())->method('ofReadingClub')->with($this->club)->willReturn($oldChapters);
        $chapterRepository->expects(self::exactly(3))->method('remove');

        $bookRepository->expects(self::once())->method('ofExternalId')->with('gb-book-b')->willReturn(null);
        $bookRepository->expects(self::once())->method('save');

        // Le nouveau livre B a 2 chapitres : seuls ceux-là doivent être (re)créés.
        $chapterRepository->expects(self::exactly(2))->method('save');

        $useCase = new SetClubBookUseCase($readingClubRepository, $bookRepository, $chapterRepository);
        $useCase->execute(10, 1, 'gb-book-b', 'Livre B', ['Auteur B'], 'fr', null, ['Roman'], 2);

        self::assertSame(2, $this->club->chapterCount());
    }

    public function testThrowsWhenRequestingUserIsNotHost(): void
    {
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $bookRepository = $this->createStub(BookRepositoryInterface::class);
        $chapterRepository = $this->createMock(ChapterRepositoryInterface::class);

        $readingClubRepository->method('ofId')->willReturn($this->club);
        $chapterRepository->expects(self::never())->method('remove');

        $this->expectException(UnauthorizedActionException::class);

        $useCase = new SetClubBookUseCase($readingClubRepository, $bookRepository, $chapterRepository);
        $useCase->execute(10, 999, 'gb-book-b', 'Livre B', ['Auteur B'], 'fr', null, ['Roman'], 2);
    }
}
