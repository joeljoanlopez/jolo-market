<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Persistence\Dbal;

use App\Receipts\Application\GetListSpending\SpendingRow;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Infrastructure\Persistence\Dbal\DbalSpendingReadModel;
use PHPUnit\Framework\Attributes\Test;

final class DbalSpendingReadModelTest extends ReceiptsDbalTestCase
{
    #[Test]
    public function it_adds_up_the_confirmed_receipts_of_the_list_per_month_and_supermarket(): void
    {
        $this->confirmed(1, '2026-11-02', 1000);
        $this->confirmed(2, '2026-11-30', 250);
        $this->confirmed(3, '2026-11-15', 2000, supermarketId: 'aldi');
        $this->confirmed(4, '2026-12-01', 700);
        $this->confirmed(5, '2026-11-03', 9999, self::OTHER_LIST);
        $this->repository->save(self::uploaded(6));

        $rows = (new DbalSpendingReadModel($this->connection))->totalsOf(ListId::fromString(self::LIST));

        usort($rows, static fn (SpendingRow $a, SpendingRow $b): int => [$a->month, $a->supermarketId] <=> [$b->month, $b->supermarketId]);
        self::assertEquals([
            new SpendingRow('2026-11', 'aldi', 2000),
            new SpendingRow('2026-11', 'mercadona', 1250),
            new SpendingRow('2026-12', 'mercadona', 700),
        ], $rows);
    }
}
