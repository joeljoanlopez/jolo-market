<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetPriceHistory;

use App\Catalog\Application\GetProduct\ProductNotFound;
use App\Catalog\Application\GetProduct\ProductViewReadModel;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Shared\Domain\ValueObject\InvalidUuid;

final readonly class GetPriceHistoryHandler
{
    public function __construct(
        private ProductViewReadModel $products,
        private PriceHistoryReadModel $history,
    ) {
    }

    public function __invoke(GetPriceHistoryQuery $query): PriceHistory
    {
        try {
            $id = ProductId::fromString($query->productId);
        } catch (InvalidUuid) {
            throw ProductNotFound::withId($query->productId);
        }

        // Unavailable products keep their history (PD-22); only an unknown product is a 404.
        if (null === $this->products->find($id)) {
            throw ProductNotFound::withId($query->productId);
        }

        $points = $this->history->pointsOf($id, $query->range->from, $query->range->before);
        // usort is stable, so snapshots captured at the same instant keep the order the read model gave.
        usort($points, static fn (PriceHistoryPoint $a, PriceHistoryPoint $b): int => $a->capturedAt <=> $b->capturedAt);

        return new PriceHistory($id->value(), $points);
    }
}
