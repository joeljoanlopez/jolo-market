<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Persistence\Dbal;

use App\Receipts\Application\GetListSpending\SpendingReadModel;
use App\Receipts\Application\GetListSpending\SpendingRow;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Adds up confirmed receipts in SQL. receipt_date is already a day in Europe/Madrid (PD-66, PD-70).
 */
final readonly class DbalSpendingReadModel implements SpendingReadModel
{
    public function __construct(private Connection $connection)
    {
    }

    public function totalsOf(ListId $listId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(receipt_date, '%Y-%m') AS month, supermarket_id, SUM(total_cents) AS total_cents
             FROM receipt
             WHERE list_id = ? AND status = ? AND receipt_date IS NOT NULL AND total_cents IS NOT NULL
             GROUP BY month, supermarket_id",
            [BinaryUuid::toDatabase($listId->value()), ReceiptStatus::CONFIRMED->value],
            [ParameterType::BINARY, ParameterType::STRING],
        );

        return array_map(static fn (array $row): SpendingRow => new SpendingRow(
            DbalValue::string($row['month']),
            DbalValue::string($row['supermarket_id']),
            DbalValue::int($row['total_cents']),
        ), $rows);
    }
}
