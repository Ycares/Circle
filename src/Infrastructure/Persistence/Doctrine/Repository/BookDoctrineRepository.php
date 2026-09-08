<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Book;
use App\Domain\Repository\BookRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class BookDoctrineRepository implements BookRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(Book $book): void
    {
        $this->entityManager->persist($book);
        $this->entityManager->flush();
    }

    public function ofId(int $id): ?Book
    {
        return $this->entityManager->find(Book::class, $id);
    }

    public function ofExternalId(string $externalId): ?Book
    {
        return $this->entityManager->getRepository(Book::class)->findOneBy(['externalId' => $externalId]);
    }
}
