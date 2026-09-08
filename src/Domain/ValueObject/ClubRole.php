<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum ClubRole: string
{
    case HOST = 'host';
    case MODERATOR = 'moderator';
    case MEMBER = 'member';
}
