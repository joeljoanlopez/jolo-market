<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListReceipts;

use App\Receipts\Application\ListAccess;
use App\Receipts\Application\ReceiptIds;
use App\Receipts\Application\ReceiptSupermarkets;
use App\Receipts\Application\ReceiptUserDirectory;
use App\Receipts\Application\ReceiptViews;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Shared\Domain\ValueObject\UserId;

/**
 * PD-70: the confirmed receipts of a list, newest first, and apart the ones still being read or
 * waiting for review, newest upload first. Confirmed receipts of the same day go by upload.
 */
final readonly class GetListReceiptsHandler
{
    public function __construct(
        private ListAccess $access,
        private ReceiptRepository $receipts,
        private ReceiptUserDirectory $users,
        private ReceiptSupermarkets $supermarkets,
    ) {
    }

    public function __invoke(GetListReceiptsQuery $query): ListReceiptsView
    {
        $listId = ReceiptIds::list($query->listId);
        $this->access->assertCanAccess(UserId::fromString($query->actorId), $listId);

        $receipts = $this->receipts->findByList($listId);
        $uploaders = [];
        $supermarkets = [];
        foreach ($receipts as $receipt) {
            $uploaders[$receipt->uploaderId()->value()] = $receipt->uploaderId();
            $supermarkets[$receipt->supermarketId()->value()] = $receipt->supermarketId();
        }
        $usernames = [] === $uploaders ? [] : $this->users->usernamesOf(array_values($uploaders));
        $names = [] === $supermarkets ? [] : $this->supermarkets->namesOf(array_values($supermarkets));

        $pending = array_values(array_filter($receipts, static fn (Receipt $receipt): bool => !$receipt->isConfirmed()));
        $confirmed = array_values(array_filter($receipts, static fn (Receipt $receipt): bool => $receipt->isConfirmed()));
        usort($pending, static fn (Receipt $a, Receipt $b): int => [$b->uploadedAt(), $b->id()->value()] <=> [$a->uploadedAt(), $a->id()->value()]);
        usort($confirmed, static fn (Receipt $a, Receipt $b): int => [$b->date()?->value(), $b->uploadedAt(), $b->id()->value()] <=> [$a->date()?->value(), $a->uploadedAt(), $a->id()->value()]);

        $summary = static fn (Receipt $receipt): ReceiptSummary => ReceiptViews::summary($receipt, $usernames, $names);

        return new ListReceiptsView(array_map($summary, $pending), array_map($summary, $confirmed));
    }
}
