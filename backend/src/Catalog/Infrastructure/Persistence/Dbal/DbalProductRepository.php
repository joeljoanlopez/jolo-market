<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Port\ProductRepository;
use App\Catalog\Domain\ValueObject\CategoryPath;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\ProductDetails;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Domain\ValueObject\UnitOfMeasure;
use App\Catalog\Domain\ValueObject\Url;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalProductRepository implements ProductRepository
{
    private const string CATEGORY_SEPARATOR = ' > ';

    private const string COLUMNS = 'id, supermarket_id, external_id, name, brand, category, image_url,
        package_quantity, package_unit, price_cents, unit_price_cents, unit_price_unit, available, last_seen_at';

    public function __construct(private Connection $connection)
    {
    }

    public function save(Product $product): void
    {
        $details = $product->details();
        $packaging = $product->packaging();
        $unitPrice = $product->unitPrice();

        $this->connection->executeStatement(
            'INSERT INTO product ('.self::COLUMNS.')
             VALUES (:id, :supermarket_id, :external_id, :name, :brand, :category, :image_url,
                     :package_quantity, :package_unit, :price_cents, :unit_price_cents, :unit_price_unit, :available, :last_seen_at)
             AS new
             ON DUPLICATE KEY UPDATE
                name = new.name, brand = new.brand, category = new.category, image_url = new.image_url,
                package_quantity = new.package_quantity, package_unit = new.package_unit,
                price_cents = new.price_cents, unit_price_cents = new.unit_price_cents, unit_price_unit = new.unit_price_unit,
                available = new.available, last_seen_at = new.last_seen_at',
            [
                'id' => BinaryUuid::toDatabase($product->id()->value()),
                'supermarket_id' => $product->supermarketId()->value(),
                'external_id' => $product->externalId()->value(),
                'name' => $details->name()->value(),
                'brand' => $details->brand(),
                'category' => $details->category()?->toString(),
                'image_url' => $details->imageUrl()?->value(),
                'package_quantity' => $packaging?->thousandths(),
                'package_unit' => $packaging?->unit()->value,
                'price_cents' => $product->currentPrice()->cents(),
                'unit_price_cents' => $unitPrice?->price()->cents(),
                'unit_price_unit' => $unitPrice?->unit()->value,
                'available' => $product->isAvailable(),
                'last_seen_at' => DbalDateTime::toDatabase($product->lastSeenAt()),
            ],
            ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN],
        );
    }

    public function findBySupermarketAndExternalId(SupermarketId $supermarketId, ExternalProductId $externalId): ?Product
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.' FROM product WHERE supermarket_id = ? AND external_id = ?',
            [$supermarketId->value(), $externalId->value()],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function findAvailableNotSeenSince(SupermarketId $supermarketId, \DateTimeImmutable $instant): iterable
    {
        // Fetched up front: the caller saves each product while it iterates.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM product WHERE supermarket_id = ? AND available = 1 AND last_seen_at < ?',
            [$supermarketId->value(), DbalDateTime::toDatabase($instant)],
        );

        return array_map(self::hydrate(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Product
    {
        $category = self::nullableString($row['category']);
        $imageUrl = self::nullableString($row['image_url']);
        $packageUnit = self::nullableString($row['package_unit']);

        return Product::reconstitute(
            ProductId::fromString(BinaryUuid::fromDatabase(self::string($row['id']))),
            SupermarketId::fromString(self::string($row['supermarket_id'])),
            ExternalProductId::fromString(self::string($row['external_id'])),
            ProductDetails::of(
                ProductName::fromString(self::string($row['name'])),
                self::nullableString($row['brand']),
                null === $category ? null : CategoryPath::fromSegments(explode(self::CATEGORY_SEPARATOR, $category)),
                null === $imageUrl ? null : Url::fromString($imageUrl),
            ),
            Money::eur(self::int($row['price_cents'])),
            null === $packageUnit ? null : Packaging::fromThousandths(UnitOfMeasure::from($packageUnit), self::int($row['package_quantity'])),
            DbalDateTime::fromDatabase(self::string($row['last_seen_at'])),
            1 === self::int($row['available']),
        );
    }

    private static function string(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new \UnexpectedValueException('Expected a string column.');
        }

        return $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    private static function int(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new \UnexpectedValueException('Expected an integer column.');
        }

        return (int) $value;
    }
}
