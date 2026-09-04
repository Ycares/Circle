<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum BookStatus: string
{
    case TO_READ = 'to_read';
    case READING = 'reading';
    case READ = 'read';
    case ABANDONED = 'abandoned';
}
