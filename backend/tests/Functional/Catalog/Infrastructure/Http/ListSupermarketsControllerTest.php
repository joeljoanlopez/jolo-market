<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Test;

final class ListSupermarketsControllerTest extends CatalogApiTestCase
{
    #[Test]
    public function it_lists_the_enabled_supermarkets_in_the_order_of_enabled_supermarkets(): void
    {
        // The test environment sets ENABLED_SUPERMARKETS=mercadona,aldi; bonpreu exists but is not enabled.
        $this->client->request('GET', '/api/supermarkets');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('[{"id":"mercadona","name":"Mercadona"},{"id":"aldi","name":"Aldi"}]', $this->client->getResponse()->getContent());
    }
}
