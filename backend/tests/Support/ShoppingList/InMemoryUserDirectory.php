<?php

declare(strict_types=1);

namespace App\Tests\Support\ShoppingList;

use App\ShoppingList\Application\UserDirectory;

final readonly class InMemoryUserDirectory implements UserDirectory
{
    /**
     * @param array<string, string> $usernames username by user id
     */
    public function __construct(private array $usernames)
    {
    }

    public function usernamesOf(array $userIds): array
    {
        $found = [];
        foreach ($userIds as $userId) {
            if (isset($this->usernames[$userId->value()])) {
                $found[$userId->value()] = $this->usernames[$userId->value()];
            }
        }

        return $found;
    }
}
