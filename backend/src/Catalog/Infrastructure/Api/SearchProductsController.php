<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api;

use App\Catalog\Application\SearchProducts\ProductSearchResult;
use App\Catalog\Application\SearchProducts\SearchProductsHandler;
use App\Catalog\Application\SearchProducts\SearchProductsQuery;
use App\Shared\Infrastructure\Http\InvalidQueryParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SearchProductsController
{
    private const int PAGE_SIZE = 20;

    public function __construct(private SearchProductsHandler $searchProducts)
    {
    }

    #[Route('/api/products', name: 'api_products_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $page = ($this->searchProducts)(new SearchProductsQuery(
            text: $request->query->getString('q'),
            supermarket: $request->query->has('supermarket') ? $request->query->getString('supermarket') : null,
            page: self::page($request),
            perPage: self::PAGE_SIZE,
        ));

        return new JsonResponse([
            'items' => array_map(static fn (ProductSearchResult $result): array => ProductJson::item($result), $page->results),
            'page' => $page->page,
            'perPage' => $page->perPage,
            'total' => $page->total,
            // An object even when empty, so clients always get a unit → cents map.
            'cheapestUnitPriceCents' => (object) $page->cheapestUnitPriceCentsByUnit,
        ]);
    }

    private static function page(Request $request): int
    {
        if (!$request->query->has('page')) {
            return 1;
        }

        $value = $request->query->getString('page');
        $page = filter_var($value, \FILTER_VALIDATE_INT);
        if (false === $page) {
            throw InvalidQueryParameter::notAnInteger('page', $value);
        }

        return $page;
    }
}
