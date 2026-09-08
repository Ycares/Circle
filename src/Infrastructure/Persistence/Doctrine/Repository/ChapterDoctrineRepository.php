<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Chapter;
use App\Domain\Entity\ReadingClub;
use App\Domain\Repository\ChapterRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ChapterDoctrineRepository implements ChapterRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(Chapter $chapter): void
    {
        $this->entityManager->persist($chapter);
        $this->entityManager->flush();
    }

    public function remove(Chapter $chapter): void
    {
        $this->entityManager->remove($chapter);
        $this->entityManager->flush();
    }

    public function ofId(int $id): ?Chapter
    {
        return $this->entityManager->find(Chapter::class, $id);
    }

    public function ofReadingClub(ReadingClub $readingClub): array
    {
        return $this->entityManager->getRepository(Chapter::class)->findBy(['readingClub' => $readingClub]);
    }
}
