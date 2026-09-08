<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\ReportStatus;

final class Report
{
    private ?int $id = null;
    private ReportStatus $status;

    public function __construct(
        private string $reportedContentType,
        private int $reportedContentId,
        private User $reporter,
        private string $reason,
        private \DateTimeImmutable $createdAt,
    ) {
        $this->status = ReportStatus::PENDING;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function reportedContentType(): string
    {
        return $this->reportedContentType;
    }

    public function reportedContentId(): int
    {
        return $this->reportedContentId;
    }

    public function reporter(): User
    {
        return $this->reporter;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): ReportStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
