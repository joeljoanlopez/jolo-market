<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

/**
 * Receipt lifecycle (PD-65): the worker reads it, members review the draft and confirm it.
 * Only confirmed receipts count as spending (PD-70).
 */
enum ReceiptStatus: string
{
    case READING = 'reading';
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
}
