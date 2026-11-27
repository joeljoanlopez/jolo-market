<?php

declare(strict_types=1);

namespace App\Receipts\Application\UploadReceipt;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * PD-68: one JPEG, PNG or WebP photo of up to 10 MB.
 */
final class InvalidReceiptPhoto extends InvalidArgument
{
    public static function notAnImage(): self
    {
        return new self('A receipt photo must be a JPEG, PNG or WebP image.');
    }

    public static function tooBig(int $maxBytes): self
    {
        return new self(\sprintf('A receipt photo must be at most %d bytes.', $maxBytes));
    }
}
