<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class ClubNotJoinableException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce club n\'est pas accessible directement (invitation ou demande requise).');
    }
}
