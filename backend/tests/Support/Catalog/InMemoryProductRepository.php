<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Port\ProductRepository;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Stores copies, so a change that is not saved is not visible, as with a real database.
 */
final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<string, Product> keyed by supermarket and external id */
    private array $products = [];

    public function save(Product $product): void
    {
        $this->products[self::key($product->supermarketId(), $product->externalId())] = clone $product;
    }

    public function findBySupermarketAndExternalId(SupermarketId $supermarketId, ExternalProductId $externalId): ?Product
    {
        $product = $this->products[self::key($supermarketId, $externalId)] ?? null;

        return null === $product ? null : clone $product;
    }

    public function findAvailableNotSeenSince(SupermarketId $supermarketId, \DateTimeImmutable $instant): iterable
    {
        foreach ($this->products as $product) {
            if ($product->supermarketId()->equals($supermarketId) && $product->isAvailable() && $product->lastSeenAt() < $instant) {
                yield clone $product;
            }
        }
    }

    /**
     * @return list<Product>
     */
    public function all(): array
    {
        return array_values(array_map(static fn (Product $product): Product => clone $product, $this->products));
    }

    public function get(string $supermarketId, string $externalId): Product
    {
        return $this->findBySupermarketAndExternalId(SupermarketId::fromString($supermarketId), ExternalProductId::fromString($externalId))
            ?? throw new \LogicException(\sprintf('No product %s/%s was saved.', $supermarketId, $externalId));
    }

    private static function key(SupermarketId $supermarketId, ExternalProductId $externalId): string
    {
        return $supermarketId->value()."\0".$externalId->value();
    }
}
