<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final class Review
{
    private ?int $id = null;

    public function __construct(
        private UserBook $userBook,
        private string $text,
        private bool $containsSpoiler,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function userBook(): UserBook
    {
        return $this->userBook;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function containsSpoiler(): bool
    {
        return $this->containsSpoiler;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
