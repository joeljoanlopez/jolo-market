<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListSpending;

final readonly class GetListSpendingQuery
{
    public function __construct(
        public string $actorId,
        public string $listId,
    ) {
    }
}
