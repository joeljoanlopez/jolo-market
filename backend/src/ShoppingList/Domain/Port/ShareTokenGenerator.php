<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Port;

use App\ShoppingList\Domain\ValueObject\ShareToken;

/**
 * Source of new share tokens: at least 128 random bits, URL-safe (PD-2).
 */
interface ShareTokenGenerator
{
    public function generate(): ShareToken;
}
