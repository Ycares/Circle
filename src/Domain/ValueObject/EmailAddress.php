<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidEmailAddressException;

final class EmailAddress
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ('' === $value || false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailAddressException($value);
        }

        $this->value = mb_strtolower($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
