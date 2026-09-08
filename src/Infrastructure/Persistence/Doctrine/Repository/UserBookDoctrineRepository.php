<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Book;
use App\Domain\Entity\User;
use App\Domain\Entity\UserBook;
use App\Domain\Repository\UserBookRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class UserBookDoctrineRepository implements UserBookRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(UserBook $userBook): void
    {
        $this->entityManager->persist($userBook);
        $this->entityManager->flush();
    }

    public function ofId(int $id): ?UserBook
    {
        return $this->entityManager->find(UserBook::class, $id);
    }

    public function ofUserAndBook(User $user, Book $book): ?UserBook
    {
        return $this->entityManager->getRepository(UserBook::class)->findOneBy(['user' => $user, 'book' => $book]);
    }

    public function ofUser(User $user): array
    {
        return $this->entityManager->getRepository(UserBook::class)->findBy(['user' => $user]);
    }
}
