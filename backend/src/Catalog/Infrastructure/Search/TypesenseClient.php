<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use App\Catalog\Application\SearchProducts\SearchUnavailable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The few calls of the Typesense REST API that search needs (PD-100). Any transport error or error status is a
 * SearchUnavailable (PD-105), except a 404 asked for with requestOrNull().
 */
final readonly class TypesenseClient
{
    private const float TIMEOUT_IN_SECONDS = 5.0;

    /** Importing a batch of documents takes longer than a search. */
    private const float IMPORT_TIMEOUT_IN_SECONDS = 60.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private string $apiKey,
    ) {
    }

    /**
     * @param array<string, scalar>     $query
     * @param array<string, mixed>|null $json
     *
     * @return array<mixed>
     *
     * @throws SearchUnavailable
     */
    public function request(string $method, string $path, array $query = [], ?array $json = null): array
    {
        return $this->requestOrNull($method, $path, $query, $json)
            ?? throw SearchUnavailable::because(\sprintf('%s %s answered 404.', $method, $path));
    }

    /**
     * @param array<string, scalar>     $query
     * @param array<string, mixed>|null $json
     *
     * @return array<mixed>|null null when Typesense answers 404
     *
     * @throws SearchUnavailable
     */
    public function requestOrNull(string $method, string $path, array $query = [], ?array $json = null): ?array
    {
        $options = ['query' => $query];
        if (null !== $json) {
            $options['json'] = $json;
        }

        $body = $this->send($method, $path, $options, self::TIMEOUT_IN_SECONDS, allowNotFound: true);
        if (null === $body) {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw SearchUnavailable::because(\sprintf('%s %s answered invalid JSON.', $method, $path), $exception);
        }

        return \is_array($decoded) ? $decoded : throw SearchUnavailable::because(\sprintf('%s %s answered no JSON object.', $method, $path));
    }

    /**
     * @param list<array<string, mixed>> $documents
     *
     * @throws SearchUnavailable when the import fails or any document is rejected
     */
    public function importDocuments(string $collection, array $documents): void
    {
        if ([] === $documents) {
            return;
        }

        $lines = array_map(static fn (array $document): string => json_encode($document, \JSON_THROW_ON_ERROR), $documents);
        $path = '/collections/'.$collection.'/documents/import';
        $body = $this->send('POST', $path, [
            'query' => ['action' => 'create'],
            'body' => implode("\n", $lines),
            'headers' => ['Content-Type' => 'text/plain'],
        ], self::IMPORT_TIMEOUT_IN_SECONDS) ?? '';

        foreach (explode("\n", trim($body)) as $line) {
            $result = json_decode($line, true);
            if (!\is_array($result) || true !== ($result['success'] ?? null)) {
                throw SearchUnavailable::because(\sprintf('importing into %s failed: %s', $collection, $line));
            }
        }
    }

    /**
     * @return list<array<mixed>>
     *
     * @throws SearchUnavailable
     */
    public function exportDocuments(string $collection): array
    {
        $body = $this->send('GET', '/collections/'.$collection.'/documents/export', [], self::IMPORT_TIMEOUT_IN_SECONDS) ?? '';

        $documents = [];
        foreach (explode("\n", trim($body)) as $line) {
            $document = '' === $line ? null : json_decode($line, true);
            if (\is_array($document)) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws SearchUnavailable
     */
    private function send(string $method, string $path, array $options, float $timeout, bool $allowNotFound = false): ?string
    {
        try {
            $response = $this->httpClient->request($method, rtrim($this->url, '/').$path, [
                ...$options,
                'headers' => ['X-TYPESENSE-API-KEY' => $this->apiKey, ...(\is_array($options['headers'] ?? null) ? $options['headers'] : [])],
                'timeout' => $timeout,
                'max_duration' => $timeout,
            ]);
            $status = $response->getStatusCode();
            if ($allowNotFound && 404 === $status) {
                return null;
            }
            if ($status >= 300) {
                throw SearchUnavailable::because(\sprintf('%s %s answered %d: %s', $method, $path, $status, $response->getContent(false)));
            }

            return $response->getContent();
        } catch (ExceptionInterface $exception) {
            throw SearchUnavailable::because(\sprintf('%s %s failed: %s', $method, $path, $exception->getMessage()), $exception);
        }
    }
}
