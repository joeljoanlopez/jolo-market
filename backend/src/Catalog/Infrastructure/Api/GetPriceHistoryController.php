<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api;

use App\Catalog\Application\GetPriceHistory\GetPriceHistoryHandler;
use App\Catalog\Application\GetPriceHistory\GetPriceHistoryQuery;
use App\Catalog\Application\GetPriceHistory\PriceHistoryPoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GetPriceHistoryController
{
    public function __construct(private GetPriceHistoryHandler $getPriceHistory)
    {
    }

    #[Route('/api/products/{id}/price-history', name: 'api_products_price_history', methods: ['GET'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $history = ($this->getPriceHistory)(new GetPriceHistoryQuery(
            $id,
            from: $request->query->has('from') ? $request->query->getString('from') : null,
            to: $request->query->has('to') ? $request->query->getString('to') : null,
        ));

        return new JsonResponse([
            'productId' => $history->productId,
            'points' => array_map(
                static fn (PriceHistoryPoint $point): array => [
                    'capturedAt' => $point->capturedAt->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM),
                    'priceCents' => $point->priceCents,
                    'unitPrice' => null === $point->unitPriceCents || null === $point->unitPriceUnit
                        ? null
                        : ['cents' => $point->unitPriceCents, 'unit' => $point->unitPriceUnit],
                ],
                $history->points,
            ),
        ]);
    }
}
