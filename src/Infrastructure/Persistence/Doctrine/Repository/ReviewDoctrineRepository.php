<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Review;
use App\Domain\Entity\UserBook;
use App\Domain\Repository\ReviewRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ReviewDoctrineRepository implements ReviewRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(Review $review): void
    {
        $this->entityManager->persist($review);
        $this->entityManager->flush();
    }

    public function ofUserBook(UserBook $userBook): array
    {
        return $this->entityManager->getRepository(Review::class)->findBy(['userBook' => $userBook]);
    }
}
