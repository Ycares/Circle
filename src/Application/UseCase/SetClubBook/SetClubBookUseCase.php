<?php

declare(strict_types=1);

namespace App\Application\UseCase\SetClubBook;

use App\Domain\Entity\Book;
use App\Domain\Entity\Chapter;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\ChapterRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;

final class SetClubBookUseCase
{
    public function __construct(
        private readonly ReadingClubRepositoryInterface $readingClubRepository,
        private readonly BookRepositoryInterface $bookRepository,
        private readonly ChapterRepositoryInterface $chapterRepository,
    ) {
    }

    /**
     * @param list<string> $authors
     * @param list<string> $genres
     */
    public function execute(
        int $readingClubId,
        int $hostUserId,
        string $externalId,
        string $title,
        array $authors,
        string $language,
        ?string $coverUrl,
        array $genres,
        int $chapterCount,
    ): void {
        $readingClub = $this->readingClubRepository->ofId($readingClubId);

        if (null === $readingClub) {
            throw new EntityNotFoundException('ReadingClub', $readingClubId);
        }

        if ($readingClub->host()->id() !== $hostUserId) {
            throw new UnauthorizedActionException('Seul l\'hôte du club peut assigner un livre en cours.');
        }

        foreach ($this->chapterRepository->ofReadingClub($readingClub) as $chapter) {
            $this->chapterRepository->remove($chapter);
        }

        $book = $this->bookRepository->ofExternalId($externalId);

        if (null === $book) {
            $book = new Book($title, $authors, $language, $coverUrl, $externalId, $genres, new \DateTimeImmutable());
            $this->bookRepository->save($book);
        }

        $readingClub->setCurrentBook($book, $chapterCount);
        $this->readingClubRepository->save($readingClub);

        for ($chapterNumber = 1; $chapterNumber <= $chapterCount; ++$chapterNumber) {
            $this->chapterRepository->save(new Chapter($readingClub, $chapterNumber));
        }
    }
}
