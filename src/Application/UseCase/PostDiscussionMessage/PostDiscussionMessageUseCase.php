<?php

declare(strict_types=1);

namespace App\Application\UseCase\PostDiscussionMessage;

use App\Domain\Entity\Discussion;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\ChapterRepositoryInterface;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\DiscussionRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

final class PostDiscussionMessageUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ReadingClubRepositoryInterface $readingClubRepository,
        private readonly ChapterRepositoryInterface $chapterRepository,
        private readonly ClubMembershipRepositoryInterface $clubMembershipRepository,
        private readonly DiscussionRepositoryInterface $discussionRepository,
    ) {
    }

    public function execute(int $authorUserId, int $readingClubId, ?int $chapterId, string $text): void
    {
        $author = $this->userRepository->ofId($authorUserId);

        if (null === $author) {
            throw new EntityNotFoundException('User', $authorUserId);
        }

        $readingClub = $this->readingClubRepository->ofId($readingClubId);

        if (null === $readingClub) {
            throw new EntityNotFoundException('ReadingClub', $readingClubId);
        }

        if (null === $this->clubMembershipRepository->ofUserAndClub($author, $readingClub)) {
            throw new UnauthorizedActionException('Seuls les membres du club peuvent poster dans une discussion.');
        }

        $chapter = null;

        if (null !== $chapterId) {
            $chapter = $this->chapterRepository->ofId($chapterId);

            if (null === $chapter || $chapter->readingClub()->id() !== $readingClub->id()) {
                throw new EntityNotFoundException('Chapter', $chapterId);
            }
        }

        $this->discussionRepository->save(new Discussion($readingClub, $chapter, $author, $text, new \DateTimeImmutable()));
    }
}
