<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\ConfigurationCheck;

/**
 * TYPESENSE_URL and TYPESENSE_API_KEY (PD-100): without them search would only fail at the first query.
 */
final readonly class SearchConfigurationCheck implements ConfigurationCheck
{
    public function __construct(private ?string $url, private ?string $apiKey)
    {
    }

    public function problems(): array
    {
        $problems = [];

        if (!self::isHttpUrl($this->url ?? '')) {
            $problems[] = \sprintf('TYPESENSE_URL is invalid: "%s" is not an http or https URL, for example http://typesense:8108.', $this->url ?? '');
        }

        if ('' === trim($this->apiKey ?? '')) {
            $problems[] = 'TYPESENSE_API_KEY is missing: set the API key of the Typesense service, for example with openssl rand -hex 32.';
        }

        return $problems;
    }

    private static function isHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (false === $parts) {
            return false;
        }

        return \in_array($parts['scheme'] ?? null, ['http', 'https'], true) && '' !== ($parts['host'] ?? '');
    }
}
