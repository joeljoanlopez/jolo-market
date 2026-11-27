<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Application\ListAccess;
use App\Receipts\Application\ReceiptListNotFound;
use App\Receipts\Domain\ValueObject\ListId;
use App\Shared\Domain\ValueObject\UserId;

final class FakeListAccess implements ListAccess
{
    /** @var array<string, list<string>> user ids by list id */
    private array $people = [];

    public function allow(string $listId, string ...$userIds): void
    {
        $this->people[$listId] = [...($this->people[$listId] ?? []), ...array_values($userIds)];
    }

    public function removeList(string $listId): void
    {
        unset($this->people[$listId]);
    }

    public function assertCanAccess(UserId $actorId, ListId $listId): void
    {
        if (!\in_array($actorId->value(), $this->people[$listId->value()] ?? [], true)) {
            throw ReceiptListNotFound::withId($listId->value());
        }
    }
}
