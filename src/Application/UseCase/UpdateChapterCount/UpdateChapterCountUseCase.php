<?php

declare(strict_types=1);

namespace App\Application\UseCase\UpdateChapterCount;

use App\Domain\Entity\Chapter;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\ChapterRepositoryInterface;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;

final class UpdateChapterCountUseCase
{
    public function __construct(
        private readonly ReadingClubRepositoryInterface $readingClubRepository,
        private readonly ChapterRepositoryInterface $chapterRepository,
        private readonly ClubMembershipRepositoryInterface $clubMembershipRepository,
    ) {
    }

    public function execute(int $readingClubId, int $hostUserId, int $newChapterCount): void
    {
        $readingClub = $this->readingClubRepository->ofId($readingClubId);

        if (null === $readingClub) {
            throw new EntityNotFoundException('ReadingClub', $readingClubId);
        }

        if ($readingClub->host()->id() !== $hostUserId) {
            throw new UnauthorizedActionException('Seul l\'hôte du club peut corriger le nombre de chapitres.');
        }

        $currentChapterCount = $readingClub->chapterCount();

        if (null === $currentChapterCount) {
            throw new UnauthorizedActionException('Ce club n\'a pas encore de livre en cours.');
        }

        if ($newChapterCount < $currentChapterCount) {
            foreach ($this->chapterRepository->ofReadingClub($readingClub) as $chapter) {
                if ($chapter->chapterNumber() > $newChapterCount) {
                    $this->chapterRepository->remove($chapter);
                }
            }
        } elseif ($newChapterCount > $currentChapterCount) {
            for ($chapterNumber = $currentChapterCount + 1; $chapterNumber <= $newChapterCount; ++$chapterNumber) {
                $this->chapterRepository->save(new Chapter($readingClub, $chapterNumber));
            }
        }

        $readingClub->setChapterCount($newChapterCount);
        $this->readingClubRepository->save($readingClub);

        foreach ($this->clubMembershipRepository->ofReadingClub($readingClub) as $membership) {
            if ($membership->declaredChapter() > $newChapterCount) {
                $membership->declareChapter($newChapterCount);
                $this->clubMembershipRepository->save($membership);
            }
        }
    }
}
