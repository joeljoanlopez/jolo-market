<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Identity;

use App\Identity\Application\UserDirectory\FindUsernamesHandler;
use App\Identity\Application\UserDirectory\FindUsernamesQuery;
use App\Receipts\Application\ReceiptUserDirectory;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Reads usernames through Identity's application query API (E3.9), never its domain (Deptrac).
 */
final readonly class IdentityReceiptUserDirectory implements ReceiptUserDirectory
{
    public function __construct(private FindUsernamesHandler $findUsernames)
    {
    }

    public function usernamesOf(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        return ($this->findUsernames)(new FindUsernamesQuery(array_map(static fn (UserId $id): string => $id->value(), $userIds)));
    }
}
