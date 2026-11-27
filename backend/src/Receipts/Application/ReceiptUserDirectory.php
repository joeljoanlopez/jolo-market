<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Shared\Domain\ValueObject\UserId;

/**
 * Port to the Identity context: receipts show who uploaded them (PD-63).
 */
interface ReceiptUserDirectory
{
    /**
     * @param list<UserId> $userIds
     *
     * @return array<string, string> username by user id; unknown ids are left out
     */
    public function usernamesOf(array $userIds): array;
}
