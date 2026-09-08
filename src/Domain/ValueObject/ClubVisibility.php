<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum ClubVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE_INVITE = 'private_invite';
    case PRIVATE_REQUEST = 'private_request';
}
