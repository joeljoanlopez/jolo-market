<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\ListSupermarkets\SupermarketNames;
use App\Catalog\Domain\ValueObject\SupermarketId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DbalSupermarketNames implements SupermarketNames
{
    public function __construct(private Connection $connection)
    {
    }

    public function namesOf(array $supermarkets): array
    {
        if ([] === $supermarkets) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name FROM supermarket WHERE id IN (:ids)',
            ['ids' => array_map(static fn (SupermarketId $id): string => $id->value(), $supermarkets)],
            ['ids' => ArrayParameterType::STRING],
        );

        $names = [];
        foreach ($rows as $row) {
            $names[DbalColumn::string($row['id'])] = DbalColumn::string($row['name']);
        }

        return $names;
    }
}
