<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class BookAlreadyInLibraryException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce livre est déjà présent dans la bibliothèque de cet utilisateur.');
    }
}
