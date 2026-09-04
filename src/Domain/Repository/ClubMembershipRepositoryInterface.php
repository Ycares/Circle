<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;

interface ClubMembershipRepositoryInterface
{
    public function save(ClubMembership $membership): void;

    public function ofUserAndClub(User $user, ReadingClub $readingClub): ?ClubMembership;

    /**
     * @return list<ClubMembership>
     */
    public function ofReadingClub(ReadingClub $readingClub): array;
}
