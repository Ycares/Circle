<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final class Discussion
{
    private ?int $id = null;

    public function __construct(
        private ReadingClub $readingClub,
        private ?Chapter $chapter,
        private User $author,
        private string $text,
        private \DateTimeImmutable $createdAt,
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

    public function chapter(): ?Chapter
    {
        return $this->chapter;
    }

    public function author(): User
    {
        return $this->author;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
