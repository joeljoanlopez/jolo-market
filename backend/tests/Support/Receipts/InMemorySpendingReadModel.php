<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Application\GetListSpending\SpendingReadModel;
use App\Receipts\Application\GetListSpending\SpendingRow;
use App\Receipts\Domain\ValueObject\ListId;

/**
 * Adds up the confirmed receipts kept by the in-memory repository, like the SQL read model.
 */
final readonly class InMemorySpendingReadModel implements SpendingReadModel
{
    public function __construct(private InMemoryReceiptRepository $receipts)
    {
    }

    public function totalsOf(ListId $listId): array
    {
        $totals = [];
        foreach ($this->receipts->findByList($listId) as $receipt) {
            $date = $receipt->date();
            $total = $receipt->total();
            if (!$receipt->isConfirmed() || null === $date || null === $total) {
                continue;
            }
            $key = $date->month().'|'.$receipt->supermarketId()->value();
            $totals[$key] = ($totals[$key] ?? 0) + $total->cents();
        }

        $rows = [];
        foreach ($totals as $key => $cents) {
            [$month, $supermarketId] = explode('|', $key);
            $rows[] = new SpendingRow($month, $supermarketId, $cents);
        }

        return $rows;
    }
}
