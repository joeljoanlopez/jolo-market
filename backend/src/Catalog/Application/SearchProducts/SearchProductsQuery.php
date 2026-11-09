<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

final readonly class SearchProductsQuery
{
    public const int MIN_TEXT_LENGTH = 2;

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
        if (mb_strlen(trim($text)) < self::MIN_TEXT_LENGTH) {
            throw InvalidSearchProductsQuery::textTooShort(self::MIN_TEXT_LENGTH);
        }

        if ($page < 1) {
            throw InvalidSearchProductsQuery::pageBelowOne($page);
        }

        if ($perPage < 1) {
            throw InvalidSearchProductsQuery::pageSizeBelowOne($perPage);
        }
    }
}
