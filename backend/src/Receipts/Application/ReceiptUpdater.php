<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Model\ReceiptLineNotFound;
use App\Receipts\Domain\Port\ReceiptChangedConcurrently;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\ReceiptLineId;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Loads a receipt of a list the actor may see (PD-63), applies one edit and saves it. When someone
 * else saved it first, the edit runs again on a fresh copy, like list edits (PD-43).
 */
final readonly class ReceiptUpdater
{
    public const int MAX_ATTEMPTS = 5;

    public function __construct(
        private ListAccess $access,
        private ReceiptRepository $receipts,
    ) {
    }

    /**
     * @throws ReceiptListNotFound when the actor may not see the list
     * @throws ReceiptNotFound     when the receipt is unknown, malformed or of another list
     */
    public function load(string $actorId, string $listId, string $receiptId): Receipt
    {
        $list = ReceiptIds::list($listId);
        $this->access->assertCanAccess(UserId::fromString($actorId), $list);

        try {
            $id = ReceiptId::fromString($receiptId);
        } catch (InvalidUuid) {
            throw ReceiptNotFound::withId($receiptId);
        }
        $receipt = $this->receipts->findById($id);
        if (null === $receipt || !$receipt->listId()->equals($list)) {
            throw ReceiptNotFound::withId($receiptId);
        }

        return $receipt;
    }

    /**
     * @template T
     *
     * @param callable(Receipt): T $change
     *
     * @return T
     */
    public function update(string $actorId, string $listId, string $receiptId, callable $change): mixed
    {
        for ($attempt = 1;; ++$attempt) {
            $receipt = $this->load($actorId, $listId, $receiptId);
            $result = $change($receipt);
            if (!$receipt->hasUnsavedChanges()) {
                return $result;
            }

            try {
                $this->receipts->save($receipt);

                return $result;
            } catch (ReceiptChangedConcurrently $changed) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $changed;
                }
            }
        }
    }

    public static function lineId(string $lineId): ReceiptLineId
    {
        try {
            return ReceiptLineId::fromString($lineId);
        } catch (InvalidUuid) {
            throw ReceiptLineNotFound::withId($lineId);
        }
    }
}
