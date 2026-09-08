<?php

declare(strict_types=1);

namespace App\Application\Port;

interface BookMetadataProviderInterface
{
    /**
     * @return list<array{externalId: string, title: string, authors: list<string>, coverUrl: ?string}>
     */
    public function search(string $query): array;

    public function fetchById(string $externalId): ?BookDetailsDTO;
}
