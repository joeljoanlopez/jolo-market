<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Catalog;

use App\Catalog\Application\GetProduct\GetProductsHandler;
use App\Catalog\Application\GetProduct\ProductView;
use App\Catalog\Application\SuggestProducts\SuggestionCandidate;
use App\Catalog\Application\SuggestProducts\SuggestProductsHandler;
use App\Receipts\Application\ReceiptProduct;
use App\Receipts\Application\ReceiptProductCatalog;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Receipts\Infrastructure\Catalog\CatalogReceiptProductCatalog;
use App\Tests\Support\Catalog\InMemoryProductViewReadModel;
use App\Tests\Support\Catalog\InMemorySuggestionCandidates;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogReceiptProductCatalogTest extends KernelTestCase
{
    private const string MILK = '0191bbbb-0000-7000-8000-000000000001';
    private const string WATER = '0191bbbb-0000-7000-8000-000000000002';
    private const string UNKNOWN = '0191bbbb-0000-7000-8000-000000000099';

    #[Test]
    public function the_best_match_is_the_first_suggestion_of_the_catalog(): void
    {
        $candidates = new InMemorySuggestionCandidates();
        $candidates->add('mercadona', new SuggestionCandidate(self::WATER, 'Agua mineral', null, true));
        $candidates->add('mercadona', new SuggestionCandidate(self::MILK, 'Leche entera', 'Hacendado', false));

        $catalog = $this->catalog($candidates);

        self::assertSame(self::MILK, $catalog->bestMatch(SupermarketId::fromString('mercadona'), LineText::fromString('LECHE ENT.'))?->value());
        self::assertNull($catalog->bestMatch(SupermarketId::fromString('mercadona'), LineText::fromString('DETERGENTE')));
    }

    #[Test]
    public function it_reads_products_by_id_through_the_catalog_query_api(): void
    {
        $readModel = new InMemoryProductViewReadModel();
        $readModel->add(new ProductView(self::MILK, 'Leche entera', 'Hacendado', 'mercadona', 'Mercadona', 1000, 'l', 125, 125, 'l', null, false, 'dairy-and-eggs'));
        $catalog = new CatalogReceiptProductCatalog(new SuggestProductsHandler(new InMemorySuggestionCandidates()), new GetProductsHandler($readModel));

        self::assertEquals(
            [self::MILK => new ReceiptProduct(self::MILK, 'mercadona', 'Leche entera', 'Hacendado', false)],
            $catalog->findByIds([ProductId::fromString(self::MILK), ProductId::fromString(self::UNKNOWN)]),
        );
    }

    #[Test]
    public function it_is_the_catalog_the_application_gets(): void
    {
        self::assertInstanceOf(CatalogReceiptProductCatalog::class, self::getContainer()->get(ReceiptProductCatalog::class));
    }

    private function catalog(InMemorySuggestionCandidates $candidates): CatalogReceiptProductCatalog
    {
        return new CatalogReceiptProductCatalog(new SuggestProductsHandler($candidates), new GetProductsHandler(new InMemoryProductViewReadModel()));
    }
}
