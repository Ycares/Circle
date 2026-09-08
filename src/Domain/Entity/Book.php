<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final class Book
{
    private ?int $id = null;

    /**
     * @param list<string> $authors
     * @param list<string> $genres
     */
    public function __construct(
        private string $title,
        private array $authors,
        private string $language,
        private ?string $coverUrl,
        private string $externalId,
        private array $genres,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return list<string>
     */
    public function authors(): array
    {
        return $this->authors;
    }

    public function language(): string
    {
        return $this->language;
    }

    public function coverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    /**
     * @return list<string>
     */
    public function genres(): array
    {
        return $this->genres;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
