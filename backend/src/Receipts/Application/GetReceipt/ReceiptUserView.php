<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceipt;

final readonly class ReceiptUserView
{
    public function __construct(
        public string $id,
        public string $username,
    ) {
    }
}
