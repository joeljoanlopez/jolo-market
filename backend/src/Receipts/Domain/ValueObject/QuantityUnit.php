<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

/**
 * A product line is bought in whole units or by weight (PD-66).
 */
enum QuantityUnit: string
{
    case UNITS = 'units';
    case KG = 'kg';
}
