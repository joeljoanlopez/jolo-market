<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Application\ReceiptUserDirectory;

final readonly class InMemoryReceiptUserDirectory implements ReceiptUserDirectory
{
    /**
     * @param array<string, string> $usernames by user id
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
