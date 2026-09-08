<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Report;
use App\Domain\Repository\ReportRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ReportDoctrineRepository implements ReportRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(Report $report): void
    {
        $this->entityManager->persist($report);
        $this->entityManager->flush();
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Report::class)->findAll();
    }
}
