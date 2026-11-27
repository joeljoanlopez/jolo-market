<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListSpending;

use App\Receipts\Application\ListAccess;
use App\Receipts\Application\ReceiptIds;
use App\Receipts\Application\ReceiptSupermarkets;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * PD-70: what the list spent per month of the receipt date (a day in Europe/Madrid), newest month
 * first, with its breakdown per supermarket. Only confirmed receipts count.
 */
final readonly class GetListSpendingHandler
{
    public function __construct(
        private ListAccess $access,
        private SpendingReadModel $spending,
        private ReceiptSupermarkets $supermarkets,
    ) {
    }

    /**
     * @return list<MonthlySpending>
     */
    public function __invoke(GetListSpendingQuery $query): array
    {
        $listId = ReceiptIds::list($query->listId);
        $this->access->assertCanAccess(UserId::fromString($query->actorId), $listId);

        $rows = $this->spending->totalsOf($listId);
        if ([] === $rows) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $ids[$row->supermarketId] = SupermarketId::fromString($row->supermarketId);
        }
        $names = $this->supermarkets->namesOf(array_values($ids));

        /** @var array<string, list<SupermarketSpending>> $byMonth */
        $byMonth = [];
        foreach ($rows as $row) {
            $byMonth[$row->month][] = new SupermarketSpending($row->supermarketId, $names[$row->supermarketId] ?? $row->supermarketId, $row->totalCents);
        }
        krsort($byMonth, \SORT_STRING);

        $months = [];
        foreach ($byMonth as $month => $supermarkets) {
            usort($supermarkets, static fn (SupermarketSpending $a, SupermarketSpending $b): int => [$a->supermarketName, $a->supermarketId] <=> [$b->supermarketName, $b->supermarketId]);
            $total = array_sum(array_map(static fn (SupermarketSpending $spending): int => $spending->totalCents, $supermarkets));
            $months[] = new MonthlySpending((string) $month, $total, $supermarkets);
        }

        return $months;
    }
}
