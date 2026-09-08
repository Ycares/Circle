<?php

declare(strict_types=1);

namespace App\Application\UseCase\JoinReadingClub;

use App\Domain\Entity\ClubMembership;
use App\Domain\Exception\AlreadyClubMemberException;
use App\Domain\Exception\ClubNotJoinableException;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;

final class JoinReadingClubUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ReadingClubRepositoryInterface $readingClubRepository,
        private readonly ClubMembershipRepositoryInterface $clubMembershipRepository,
    ) {
    }

    public function execute(int $userId, int $readingClubId): void
    {
        $user = $this->userRepository->ofId($userId);

        if (null === $user) {
            throw new EntityNotFoundException('User', $userId);
        }

        $readingClub = $this->readingClubRepository->ofId($readingClubId);

        if (null === $readingClub) {
            throw new EntityNotFoundException('ReadingClub', $readingClubId);
        }

        if (null !== $this->clubMembershipRepository->ofUserAndClub($user, $readingClub)) {
            throw new AlreadyClubMemberException();
        }

        if (ClubVisibility::PUBLIC !== $readingClub->visibility()) {
            throw new ClubNotJoinableException();
        }

        $this->clubMembershipRepository->save(new ClubMembership($user, $readingClub, ClubRole::MEMBER, new \DateTimeImmutable()));
    }
}
