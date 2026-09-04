<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum ReportStatus: string
{
    case PENDING = 'pending';
    case REVIEWED = 'reviewed';
    case DISMISSED = 'dismissed';
}
