<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Book;

interface BookRepositoryInterface
{
    public function save(Book $book): void;

    public function ofId(int $id): ?Book;

    public function ofExternalId(string $externalId): ?Book;
}
