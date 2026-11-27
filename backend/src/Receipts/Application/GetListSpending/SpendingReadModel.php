<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListSpending;

use App\Receipts\Domain\ValueObject\ListId;

interface SpendingReadModel
{
    /**
     * The totals of the confirmed receipts of the list, per month of the receipt date and supermarket (PD-70).
     *
     * @return list<SpendingRow> in no particular order
     */
    public function totalsOf(ListId $listId): array;
}
