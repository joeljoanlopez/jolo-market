<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListReceipts;

final readonly class GetListReceiptsQuery
{
    public function __construct(
        public string $actorId,
        public string $listId,
    ) {
    }
}
