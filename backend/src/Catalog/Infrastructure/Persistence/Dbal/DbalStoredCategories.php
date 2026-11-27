<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\FindUnmappedCategories\CategoryCount;
use App\Catalog\Application\FindUnmappedCategories\StoredCategories;
use Doctrine\DBAL\Connection;

final readonly class DbalStoredCategories implements StoredCategories
{
    public function __construct(private Connection $connection)
    {
    }

    public function counts(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT supermarket_id, category, COUNT(*) AS products FROM product WHERE category IS NOT NULL GROUP BY supermarket_id, category',
        );

        return array_map(
            static fn (array $row): CategoryCount => new CategoryCount(
                DbalColumn::string($row['supermarket_id']),
                DbalColumn::string($row['category']),
                DbalColumn::int($row['products']),
            ),
            $rows,
        );
    }
}
