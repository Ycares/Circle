<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Review;
use App\Domain\Entity\UserBook;

interface ReviewRepositoryInterface
{
    public function save(Review $review): void;

    /**
     * @return list<Review>
     */
    public function ofUserBook(UserBook $userBook): array;
}
