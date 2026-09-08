<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class EntityNotFoundException extends \DomainException
{
    public function __construct(string $entityName, int|string $id)
    {
        parent::__construct(\sprintf('%s "%s" introuvable.', $entityName, $id));
    }
}
