<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\ListSupermarkets;

use App\Catalog\Application\ListSupermarkets\ListSupermarketsHandler;
use App\Catalog\Application\ListSupermarkets\ListSupermarketsQuery;
use App\Catalog\Application\ListSupermarkets\SupermarketSummary;
use App\Tests\Support\Catalog\FixedEnabledSupermarkets;
use App\Tests\Support\Catalog\InMemorySupermarketNames;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListSupermarketsHandlerTest extends TestCase
{
    private const array NAMES = ['mercadona' => 'Mercadona', 'bonpreu' => 'Bonpreu i Esclat', 'aldi' => 'Aldi'];

    #[Test]
    public function it_lists_only_the_enabled_supermarkets_in_the_configured_order(): void
    {
        $supermarkets = $this->list(enabled: ['aldi', 'mercadona']);

        self::assertEquals([new SupermarketSummary('aldi', 'Aldi'), new SupermarketSummary('mercadona', 'Mercadona')], $supermarkets);
    }

    #[Test]
    public function it_leaves_out_an_enabled_supermarket_that_is_not_in_the_catalog(): void
    {
        $supermarkets = $this->list(enabled: ['lidl', 'bonpreu']);

        self::assertEquals([new SupermarketSummary('bonpreu', 'Bonpreu i Esclat')], $supermarkets);
    }

    /**
     * @param list<string> $enabled
     *
     * @return list<SupermarketSummary>
     */
    private function list(array $enabled): array
    {
        $handler = new ListSupermarketsHandler(new FixedEnabledSupermarkets($enabled), new InMemorySupermarketNames(self::NAMES));

        return $handler(new ListSupermarketsQuery());
    }
}
