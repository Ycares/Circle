<?php

declare(strict_types=1);

namespace App\Application\UseCase\ListVisibleDiscussions;

use App\Domain\Entity\Discussion;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\DiscussionRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\SpoilerGuardService;

final class ListVisibleDiscussionsUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ReadingClubRepositoryInterface $readingClubRepository,
        private readonly ClubMembershipRepositoryInterface $clubMembershipRepository,
        private readonly DiscussionRepositoryInterface $discussionRepository,
        private readonly SpoilerGuardService $spoilerGuardService,
    ) {
    }

    /**
     * @return list<Discussion>
     */
    public function execute(int $userId, int $readingClubId): array
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

        $visibleDiscussions = array_filter(
            $this->discussionRepository->ofReadingClub($readingClub),
            fn (Discussion $discussion): bool => $this->spoilerGuardService->isDiscussionVisibleFor($discussion, $membership),
        );

        return array_values($visibleDiscussions);
    }
}
