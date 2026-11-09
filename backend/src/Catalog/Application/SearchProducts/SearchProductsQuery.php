<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

final readonly class SearchProductsQuery
{
    /**
     * @param string      $text        what the user typed
     * @param string|null $supermarket slug of the only supermarket to search; null searches every enabled one (PD-33)
     */
    public function __construct(
        public string $text,
        public ?string $supermarket = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
        if ($page < 1) {
            throw InvalidSearchProductsQuery::pageBelowOne($page);
        }

        if ($perPage < 1) {
            throw InvalidSearchProductsQuery::pageSizeBelowOne($perPage);
        }
    }
}
