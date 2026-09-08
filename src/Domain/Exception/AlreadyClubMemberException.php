<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class AlreadyClubMemberException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Cet utilisateur est déjà membre de ce club.');
    }
}
