<?php

declare(strict_types=1);

namespace App\Application\Port;

final class BookDetailsDTO
{
    /**
     * @param list<string> $authors
     * @param list<string> $genres
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly array $authors,
        public readonly string $language,
        public readonly ?string $coverUrl,
        public readonly array $genres,
    ) {
    }
}
