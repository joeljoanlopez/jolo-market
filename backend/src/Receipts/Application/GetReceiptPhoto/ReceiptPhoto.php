<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceiptPhoto;

final readonly class ReceiptPhoto
{
    public function __construct(
        public string $contents,
        public string $mediaType,
    ) {
    }
}
