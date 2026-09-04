<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Report;

interface ReportRepositoryInterface
{
    public function save(Report $report): void;

    /**
     * @return list<Report>
     */
    public function all(): array;
}
