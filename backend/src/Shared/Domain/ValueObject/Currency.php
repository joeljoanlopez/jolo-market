<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Every supported chain sells in euros. Add a case only together with the rules
 * for mixing currencies.
 */
enum Currency: string
{
    case EUR = 'EUR';
}
