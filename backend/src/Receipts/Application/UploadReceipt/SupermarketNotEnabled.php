<?php

declare(strict_types=1);

namespace App\Receipts\Application\UploadReceipt;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * PD-64: receipts are uploaded for one of the enabled supermarkets only.
 */
final class SupermarketNotEnabled extends InvalidArgument
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Supermarket "%s" is not enabled.', $id));
    }
}
