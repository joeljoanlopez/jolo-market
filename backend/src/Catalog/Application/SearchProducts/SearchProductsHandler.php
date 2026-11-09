<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

use App\Catalog\Application\IngestCatalog\SupermarketNotEnabled;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Searches the available products of the enabled supermarkets (PD-22, PD-24), optionally of one only (PD-33).
 */
final readonly class SearchProductsHandler
{
    public function __construct(
        private ProductSearchReadModel $readModel,
        private EnabledSupermarkets $enabledSupermarkets,
    ) {
    }

    public function __invoke(SearchProductsQuery $query): ProductSearchPage
    {
        return $this->readModel->search($query->text, $this->supermarketsToSearch($query), $query->page, $query->perPage);
    }

    /**
     * @return list<SupermarketId>
     */
    private function supermarketsToSearch(SearchProductsQuery $query): array
    {
        $enabled = $this->enabledSupermarkets->enabledSupermarkets();
        if (null === $query->supermarket) {
            return $enabled;
        }

        $filter = SupermarketId::fromString($query->supermarket);
        foreach ($enabled as $supermarket) {
            if ($supermarket->equals($filter)) {
                return [$supermarket];
            }
        }

        throw SupermarketNotEnabled::named($filter->value());
    }
}
