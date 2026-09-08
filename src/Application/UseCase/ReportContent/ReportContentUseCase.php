<?php

declare(strict_types=1);

namespace App\Application\UseCase\ReportContent;

use App\Domain\Entity\Report;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ReportRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

final class ReportContentUseCase
{
    private const array ALLOWED_CONTENT_TYPES = ['discussion', 'review'];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ReportRepositoryInterface $reportRepository,
    ) {
    }

    public function execute(int $reporterUserId, string $reportedContentType, int $reportedContentId, string $reason): void
    {
        $reporter = $this->userRepository->ofId($reporterUserId);

        if (null === $reporter) {
            throw new EntityNotFoundException('User', $reporterUserId);
        }

        if (!\in_array($reportedContentType, self::ALLOWED_CONTENT_TYPES, true)) {
            throw new \InvalidArgumentException('Le type de contenu signalé doit être "discussion" ou "review".');
        }

        $this->reportRepository->save(new Report($reportedContentType, $reportedContentId, $reporter, $reason, new \DateTimeImmutable()));
    }
}
