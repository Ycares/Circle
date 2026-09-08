<?php

declare(strict_types=1);

namespace App\Application\UseCase\DeclareClubProgress;

use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

final class DeclareClubProgressUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ReadingClubRepositoryInterface $readingClubRepository,
        private readonly ClubMembershipRepositoryInterface $clubMembershipRepository,
    ) {
    }

    public function execute(int $userId, int $readingClubId, int $declaredChapter): void
    {
        $user = $this->userRepository->ofId($userId);

        if (null === $user) {
            throw new EntityNotFoundException('User', $userId);
        }

        $readingClub = $this->readingClubRepository->ofId($readingClubId);

        if (null === $readingClub) {
            throw new EntityNotFoundException('ReadingClub', $readingClubId);
        }

        $membership = $this->clubMembershipRepository->ofUserAndClub($user, $readingClub);

        if (null === $membership) {
            throw new EntityNotFoundException('ClubMembership', $readingClubId);
        }

        if ($declaredChapter < 0 || (null !== $readingClub->chapterCount() && $declaredChapter > $readingClub->chapterCount())) {
            throw new \InvalidArgumentException('Le chapitre déclaré est en dehors des chapitres existants du club.');
        }

        $membership->declareChapter($declaredChapter);
        $this->clubMembershipRepository->save($membership);
    }
}
