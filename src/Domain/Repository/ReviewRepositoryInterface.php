<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Review;

interface ReviewRepositoryInterface
{
    public function save(Review $review): void;
}
