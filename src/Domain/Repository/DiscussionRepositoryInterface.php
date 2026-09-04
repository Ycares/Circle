<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Discussion;
use App\Domain\Entity\ReadingClub;

interface DiscussionRepositoryInterface
{
    public function save(Discussion $discussion): void;

    /**
     * @return list<Discussion>
     */
    public function ofReadingClub(ReadingClub $readingClub): array;
}
