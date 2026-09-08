<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ReadingClub;

interface ReadingClubRepositoryInterface
{
    public function save(ReadingClub $readingClub): void;

    public function ofId(int $id): ?ReadingClub;

    /**
     * @return list<ReadingClub>
     */
    public function all(): array;
}
