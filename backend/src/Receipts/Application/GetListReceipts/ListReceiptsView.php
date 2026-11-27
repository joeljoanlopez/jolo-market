<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListReceipts;

final readonly class ListReceiptsView
{
    /**
     * @param list<ReceiptSummary> $pending   reading and draft, newest upload first
     * @param list<ReceiptSummary> $confirmed newest receipt date first
     */
    public function __construct(
        public array $pending,
        public array $confirmed,
    ) {
    }
}
