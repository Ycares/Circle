<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Chapter;
use App\Domain\Entity\ReadingClub;

interface ChapterRepositoryInterface
{
    public function save(Chapter $chapter): void;

    public function remove(Chapter $chapter): void;

    public function ofId(int $id): ?Chapter;

    /**
     * @return list<Chapter>
     */
    public function ofReadingClub(ReadingClub $readingClub): array;
}
