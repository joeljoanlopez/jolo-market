<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\JoinList;

final readonly class JoinListCommand
{
    public function __construct(
        public string $actorId,
        #[\SensitiveParameter] public string $shareToken,
    ) {
    }
}
