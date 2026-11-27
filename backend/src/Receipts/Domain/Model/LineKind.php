<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

/**
 * A product line adds to what was bought; a discount line is subtracted (PD-66).
 */
enum LineKind: string
{
    case PRODUCT = 'product';
    case DISCOUNT = 'discount';
}
