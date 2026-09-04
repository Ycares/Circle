<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidEmailAddressException extends \InvalidArgumentException
{
    public function __construct(string $value)
    {
        parent::__construct(\sprintf('L\'adresse email "%s" n\'est pas valide.', $value));
    }
}
