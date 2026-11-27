<?php

declare(strict_types=1);

namespace App\Receipts\Application\RemoveListReceipts;

final readonly class RemoveListReceiptsCommand
{
    public function __construct(public string $listId)
    {
    }
}
