<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\ClubVisibility;

final class ReadingClub
{
    private ?int $id = null;
    private ?Book $currentBook = null;
    private ?int $chapterCount = null;

    public function __construct(
        private string $name,
        private string $description,
        private ClubVisibility $visibility,
        private User $host,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function visibility(): ClubVisibility
    {
        return $this->visibility;
    }

    public function host(): User
    {
        return $this->host;
    }

    public function currentBook(): ?Book
    {
        return $this->currentBook;
    }

    public function chapterCount(): ?int
    {
        return $this->chapterCount;
    }

    public function setCurrentBook(Book $book, int $chapterCount): void
    {
        $this->currentBook = $book;
        $this->chapterCount = $chapterCount;
    }

    public function setChapterCount(int $chapterCount): void
    {
        $this->chapterCount = $chapterCount;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
