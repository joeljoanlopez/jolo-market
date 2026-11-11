<?php

declare(strict_types=1);

namespace App\Tests\Support\ShoppingList;

use App\ShoppingList\Domain\Port\ShareTokenGenerator;
use App\ShoppingList\Domain\ValueObject\ShareToken;

/**
 * Predictable tokens: nth(1), nth(2)...
 */
final class SequentialShareTokenGenerator implements ShareTokenGenerator
{
    private int $next = 1;

    public static function nth(int $n): string
    {
        return \sprintf('share-token-%022d', $n);
    }

    public function generate(): ShareToken
    {
        return ShareToken::fromString(self::nth($this->next++));
    }
}
