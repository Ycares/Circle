<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final class Chapter
{
    private ?int $id = null;

    public function __construct(
        private ReadingClub $readingClub,
        private int $chapterNumber,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function readingClub(): ReadingClub
    {
        return $this->readingClub;
    }

    public function chapterNumber(): int
    {
        return $this->chapterNumber;
    }
}
