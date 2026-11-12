<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\GetPriceHistory\PriceHistoryPoint;
use App\Catalog\Application\GetPriceHistory\PriceHistoryReadModel;
use App\Catalog\Domain\ValueObject\ProductId;

/**
 * Returns the points in the order they were added, so the handler is the one that orders them.
 */
final class InMemoryPriceHistoryReadModel implements PriceHistoryReadModel
{
    /** @var array<string, list<PriceHistoryPoint>> */
    private array $points = [];

    public function add(string $productId, PriceHistoryPoint $point): void
    {
        $this->points[$productId][] = $point;
    }

    public function pointsOf(ProductId $id, ?\DateTimeImmutable $from, ?\DateTimeImmutable $before): array
    {
        return array_values(array_filter(
            $this->points[$id->value()] ?? [],
            static fn (PriceHistoryPoint $point): bool => (null === $from || $point->capturedAt >= $from)
                && (null === $before || $point->capturedAt < $before),
        ));
    }
}
