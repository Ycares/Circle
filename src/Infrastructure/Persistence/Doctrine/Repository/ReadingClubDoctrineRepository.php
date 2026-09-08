<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\ReadingClub;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ReadingClubDoctrineRepository implements ReadingClubRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(ReadingClub $readingClub): void
    {
        $this->entityManager->persist($readingClub);
        $this->entityManager->flush();
    }

    public function ofId(int $id): ?ReadingClub
    {
        return $this->entityManager->find(ReadingClub::class, $id);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(ReadingClub::class)->findAll();
    }
}
