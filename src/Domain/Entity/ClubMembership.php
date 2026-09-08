<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\ClubRole;

final class ClubMembership
{
    private ?int $id = null;
    private int $declaredChapter = 0;

    public function __construct(
        private User $user,
        private ReadingClub $readingClub,
        private ClubRole $role,
        private \DateTimeImmutable $joinedAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function readingClub(): ReadingClub
    {
        return $this->readingClub;
    }

    public function declaredChapter(): int
    {
        return $this->declaredChapter;
    }

    public function declareChapter(int $chapterNumber): void
    {
        $this->declaredChapter = $chapterNumber;
    }

    public function role(): ClubRole
    {
        return $this->role;
    }

    public function joinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
