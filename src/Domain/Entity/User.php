<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\EmailAddress;

final class User
{
    private ?int $id = null;

    public function __construct(
        private EmailAddress $email,
        private string $passwordHash,
        private string $pseudo,
        private string $preferredLanguage,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function pseudo(): string
    {
        return $this->pseudo;
    }

    public function preferredLanguage(): string
    {
        return $this->preferredLanguage;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
