<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\BookStatus;

final class UserBook
{
    private ?int $id = null;

    public function __construct(
        private User $user,
        private Book $book,
        private BookStatus $status,
        private ?int $rating,
        private int $progress,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        self::assertValidProgress($progress);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function book(): Book
    {
        return $this->book;
    }

    public function status(): BookStatus
    {
        return $this->status;
    }

    public function changeStatus(BookStatus $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function rating(): ?int
    {
        return $this->rating;
    }

    public function rate(?int $rating): void
    {
        $this->rating = $rating;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function progress(): int
    {
        return $this->progress;
    }

    public function updateProgress(int $progress): void
    {
        self::assertValidProgress($progress);

        $this->progress = $progress;
        $this->updatedAt = new \DateTimeImmutable();
    }

    private static function assertValidProgress(int $progress): void
    {
        if ($progress < 0 || $progress > 100) {
            throw new \InvalidArgumentException('Le pourcentage de progression doit être compris entre 0 et 100.');
        }
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
