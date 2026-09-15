<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\BookMetadata;

use App\Infrastructure\BookMetadata\GoogleBooksProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleBooksProviderTest extends TestCase
{
    public function testSearchMapsResultsFromGoogleBooksResponse(): void
    {
        $body = json_encode([
            'items' => [
                [
                    'id' => 'gb-1',
                    'volumeInfo' => [
                        'title' => 'Dune',
                        'authors' => ['Frank Herbert'],
                        'imageLinks' => ['thumbnail' => 'http://example.com/dune.jpg'],
                    ],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $httpClient = new MockHttpClient([new MockResponse($body)]);
        $logger = $this->createStub(LoggerInterface::class);

        $provider = new GoogleBooksProvider($httpClient, new ArrayAdapter(), $logger, 'test-key');
        $results = $provider->search('Dune');

        self::assertSame([
            [
                'externalId' => 'gb-1',
                'title' => 'Dune',
                'authors' => ['Frank Herbert'],
                'coverUrl' => 'http://example.com/dune.jpg',
            ],
        ], $results);
    }

    public function testSearchReturnsEmptyArrayWhenNoItems(): void
    {
        $httpClient = new MockHttpClient([new MockResponse(json_encode(['totalItems' => 0], \JSON_THROW_ON_ERROR))]);
        $logger = $this->createStub(LoggerInterface::class);

        $provider = new GoogleBooksProvider($httpClient, new ArrayAdapter(), $logger, 'test-key');

        self::assertSame([], $provider->search('inconnu'));
    }

    public function testSearchResultsAreCachedAndDoNotTriggerASecondHttpCall(): void
    {
        $body = json_encode(['items' => [['id' => 'gb-1', 'volumeInfo' => ['title' => 'Dune']]]], \JSON_THROW_ON_ERROR);

        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount, $body): MockResponse {
            ++$requestCount;

            return new MockResponse($body);
        });
        $logger = $this->createStub(LoggerInterface::class);

        $provider = new GoogleBooksProvider($httpClient, new ArrayAdapter(), $logger, 'test-key');

        $provider->search('Dune');
        $provider->search('Dune');

        self::assertSame(1, $requestCount);
    }

    public function testSearchReturnsEmptyArrayAndLogsOnHttpError(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('quota exceeded', ['http_code' => 429])]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $provider = new GoogleBooksProvider($httpClient, new ArrayAdapter(), $logger, 'test-key');

        self::assertSame([], $provider->search('Dune'));
    }

    public function testFetchByIdMapsBookDetails(): void
    {
        $body = json_encode([
            'id' => 'gb-1',
            'volumeInfo' => [
                'title' => 'Dune',
                'authors' => ['Frank Herbert'],
                'language' => 'en',
                'imageLinks' => ['thumbnail' => 'http://example.com/dune.jpg'],
                'categories' => ['Science Fiction'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $httpClient = new MockHttpClient([new MockResponse($body)]);
        $logger = $this->createStub(LoggerInterface::class);

        $provider = new GoogleBooksProvider($httpClient, new ArrayAdapter(), $logger, 'test-key');
        $details = $provider->fetchById('gb-1');

        self::assertNotNull($details);
        self::assertSame('gb-1', $details->externalId);
        self::assertSame('Dune', $details->title);
        self::assertSame(['Frank Herbert'], $details->authors);
        self::assertSame('en', $details->language);
        self::assertSame('http://example.com/dune.jpg', $details->coverUrl);
        self::assertSame(['Science Fiction'], $details->genres);
    }

    public function testFetchByIdReturnsNullAndLogsWhenNotFound(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('not found', ['http_code' => 404])]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $provider = new GoogleBooksProvider($httpClient, new ArrayAdapter(), $logger, 'test-key');

        self::assertNull($provider->fetchById('inconnu'));
    }
}
