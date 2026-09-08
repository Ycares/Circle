<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ClubMembershipDoctrineRepository implements ClubMembershipRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(ClubMembership $membership): void
    {
        $this->entityManager->persist($membership);
        $this->entityManager->flush();
    }

    public function ofUserAndClub(User $user, ReadingClub $readingClub): ?ClubMembership
    {
        return $this->entityManager->getRepository(ClubMembership::class)->findOneBy([
            'user' => $user,
            'readingClub' => $readingClub,
        ]);
    }

    public function ofReadingClub(ReadingClub $readingClub): array
    {
        return $this->entityManager->getRepository(ClubMembership::class)->findBy(['readingClub' => $readingClub]);
    }
}
