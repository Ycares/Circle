<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\ValueObject\EmailAddress;

final class EmailAlreadyRegisteredException extends \DomainException
{
    public function __construct(EmailAddress $email)
    {
        parent::__construct(\sprintf('Un compte existe déjà avec l\'adresse email "%s".', $email->value()));
    }
}
