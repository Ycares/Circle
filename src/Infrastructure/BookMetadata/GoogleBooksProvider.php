<?php

declare(strict_types=1);

namespace App\Infrastructure\BookMetadata;

use App\Application\Port\BookDetailsDTO;
use App\Application\Port\BookMetadataProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleBooksProvider implements BookMetadataProviderInterface
{
    private const string BASE_URL = 'https://www.googleapis.com/books/v1/volumes';
    private const int SEARCH_CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
    ) {
    }

    public function search(string $query): array
    {
        try {
            return $this->cache->get(
                'google_books_search_'.md5($query),
                function (ItemInterface $item) use ($query): array {
                    $item->expiresAfter(self::SEARCH_CACHE_TTL_SECONDS);

                    return $this->doSearch($query);
                },
            );
        } catch (ExceptionInterface $e) {
            $this->logger->error('Échec de la recherche Google Books.', ['query' => $query, 'exception' => $e]);

            return [];
        }
    }

    public function fetchById(string $externalId): ?BookDetailsDTO
    {
        try {
            $data = $this->httpClient
                ->request('GET', self::BASE_URL.'/'.$externalId, ['query' => ['key' => $this->apiKey]])
                ->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Échec de la récupération du livre Google Books.', ['externalId' => $externalId, 'exception' => $e]);

            return null;
        }

        $volumeInfo = $data['volumeInfo'] ?? [];

        return new BookDetailsDTO(
            (string) ($data['id'] ?? $externalId),
            (string) ($volumeInfo['title'] ?? ''),
            array_values($volumeInfo['authors'] ?? []),
            (string) ($volumeInfo['language'] ?? ''),
            $volumeInfo['imageLinks']['thumbnail'] ?? null,
            array_values($volumeInfo['categories'] ?? []),
        );
    }

    /**
     * @return list<array{externalId: string, title: string, authors: list<string>, coverUrl: ?string}>
     */
    private function doSearch(string $query): array
    {
        $data = $this->httpClient
            ->request('GET', self::BASE_URL, ['query' => ['q' => $query, 'key' => $this->apiKey]])
            ->toArray();

        $results = [];

        foreach ($data['items'] ?? [] as $item) {
            $volumeInfo = $item['volumeInfo'] ?? [];

            $results[] = [
                'externalId' => (string) ($item['id'] ?? ''),
                'title' => (string) ($volumeInfo['title'] ?? ''),
                'authors' => array_values($volumeInfo['authors'] ?? []),
                'coverUrl' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
            ];
        }

        return $results;
    }
}
