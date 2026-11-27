<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Receipts\Domain\ValueObject\ListId;
use App\Shared\Domain\ValueObject\InvalidUuid;

/**
 * Ids from requests: a malformed one names nothing, so it is a not found (PD-15).
 */
final class ReceiptIds
{
    public static function list(string $listId): ListId
    {
        try {
            return ListId::fromString($listId);
        } catch (InvalidUuid) {
            throw ReceiptListNotFound::withId($listId);
        }
    }
}
