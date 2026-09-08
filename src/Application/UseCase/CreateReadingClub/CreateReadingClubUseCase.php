<?php

declare(strict_types=1);

namespace App\Application\UseCase\CreateReadingClub;

use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;

final class CreateReadingClubUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ReadingClubRepositoryInterface $readingClubRepository,
        private readonly ClubMembershipRepositoryInterface $clubMembershipRepository,
    ) {
    }

    public function execute(int $hostUserId, string $name, string $description, string $visibility): void
    {
        $host = $this->userRepository->ofId($hostUserId);

        if (null === $host) {
            throw new EntityNotFoundException('User', $hostUserId);
        }

        $readingClub = new ReadingClub($name, $description, ClubVisibility::from($visibility), $host, new \DateTimeImmutable());
        $this->readingClubRepository->save($readingClub);

        $this->clubMembershipRepository->save(new ClubMembership($host, $readingClub, ClubRole::HOST, new \DateTimeImmutable()));
    }
}
