<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * The unit every price is normalized to, so products of different sizes can be compared.
 */
enum UnitOfMeasure: string
{
    case KILOGRAM = 'kg';
    case LITRE = 'l';
    case UNIT = 'unit';
}
