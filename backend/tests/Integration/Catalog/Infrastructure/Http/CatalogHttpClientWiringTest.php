<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\RetryableHttpClient;

final class CatalogHttpClientWiringTest extends KernelTestCase
{
    #[Test]
    public function the_container_provides_the_catalog_http_client(): void
    {
        self::bootKernel();

        self::assertInstanceOf(RetryableHttpClient::class, self::getContainer()->get('test.catalog_http_client'));
    }
}
