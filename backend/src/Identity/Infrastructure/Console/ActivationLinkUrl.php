<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

/**
 * Absolute link to the SPA activation page, for output outside an HTTP request.
 * The SPA builds the same link from its own origin when an admin works in the browser.
 */
final readonly class ActivationLinkUrl
{
    public function __construct(private string $baseUri)
    {
    }

    public function for(#[\SensitiveParameter] string $plainToken): string
    {
        return rtrim($this->baseUri, '/').'/activate/'.$plainToken;
    }
}
