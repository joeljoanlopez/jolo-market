<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Security;

use App\ShoppingList\Domain\Port\ShareTokenGenerator;
use App\ShoppingList\Domain\ValueObject\ShareToken;

/**
 * 256 random bits, base64url without padding (43 characters).
 */
final readonly class RandomShareTokenGenerator implements ShareTokenGenerator
{
    public function generate(): ShareToken
    {
        return ShareToken::fromString(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }
}
