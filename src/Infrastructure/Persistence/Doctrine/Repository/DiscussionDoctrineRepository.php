<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Discussion;
use App\Domain\Entity\ReadingClub;
use App\Domain\Repository\DiscussionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DiscussionDoctrineRepository implements DiscussionRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(Discussion $discussion): void
    {
        $this->entityManager->persist($discussion);
        $this->entityManager->flush();
    }

    public function ofReadingClub(ReadingClub $readingClub): array
    {
        return $this->entityManager->getRepository(Discussion::class)->findBy(['readingClub' => $readingClub]);
    }
}
