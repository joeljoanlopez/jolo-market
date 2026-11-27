<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\ReadBy;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\UserId;

/**
 * The photo of a supermarket receipt uploaded to a shopping list (PD-63), read by the worker
 * into a draft, reviewed and confirmed by a member (PD-65). Only confirmed receipts count as
 * spending (PD-70).
 *
 * Every change moves the version; the version it was loaded with stays apart, so saving can
 * check nobody saved or deleted it in between.
 */
final class Receipt
{
    public const int MAX_LINES = 200;

    /**
     * @param list<ReceiptLine> $lines
     */
    private function __construct(
        private readonly ReceiptId $id,
        private readonly ListId $listId,
        private readonly SupermarketId $supermarketId,
        private readonly UserId $uploaderId,
        private readonly PhotoType $photoType,
        private readonly \DateTimeImmutable $uploadedAt,
        private ReceiptStatus $status,
        private ?ReadBy $readBy,
        private ?ReceiptDate $date,
        private ?Money $total,
        private array $lines,
        private int $version,
        private readonly int $loadedVersion,
    ) {
    }

    public static function upload(ReceiptId $id, ListId $listId, SupermarketId $supermarketId, UserId $uploaderId, PhotoType $photoType, \DateTimeImmutable $uploadedAt): self
    {
        return new self($id, $listId, $supermarketId, $uploaderId, $photoType, $uploadedAt, ReceiptStatus::READING, null, null, null, [], 1, 0);
    }

    /**
     * Rebuilds a stored receipt. Only persistence adapters call it.
     *
     * @param list<ReceiptLine> $lines in their order on the receipt
     */
    public static function reconstitute(
        ReceiptId $id,
        ListId $listId,
        SupermarketId $supermarketId,
        UserId $uploaderId,
        PhotoType $photoType,
        \DateTimeImmutable $uploadedAt,
        ReceiptStatus $status,
        ?ReadBy $readBy,
        ?ReceiptDate $date,
        ?Money $total,
        array $lines,
        int $version,
    ): self {
        return new self($id, $listId, $supermarketId, $uploaderId, $photoType, $uploadedAt, $status, $readBy, $date, $total, $lines, $version, $version);
    }

    public function __clone()
    {
        $this->lines = array_map(static fn (ReceiptLine $line): ReceiptLine => clone $line, $this->lines);
    }

    /**
     * PD-65: the draft holds what was read; a date in the future cannot be right, so it is left out.
     */
    public function completeReading(ReadResult $result, \DateTimeImmutable $now): void
    {
        if (ReceiptStatus::READING !== $this->status) {
            throw ReceiptAlreadyRead::withId($this->id->value());
        }

        $this->status = ReceiptStatus::DRAFT;
        $this->readBy = $result->readBy;
        $this->date = null !== $result->date && $result->date->isAfter(ReceiptDate::today($now)) ? null : $result->date;
        $this->total = $result->total;
        $this->lines = $result->lines;
        $this->changed();
    }

    public function id(): ReceiptId
    {
        return $this->id;
    }

    public function listId(): ListId
    {
        return $this->listId;
    }

    public function supermarketId(): SupermarketId
    {
        return $this->supermarketId;
    }

    public function uploaderId(): UserId
    {
        return $this->uploaderId;
    }

    public function photoType(): PhotoType
    {
        return $this->photoType;
    }

    public function uploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function status(): ReceiptStatus
    {
        return $this->status;
    }

    /**
     * Null while it is being read (PD-114).
     */
    public function readBy(): ?ReadBy
    {
        return $this->readBy;
    }

    public function date(): ?ReceiptDate
    {
        return $this->date;
    }

    public function total(): ?Money
    {
        return $this->total;
    }

    /**
     * @return list<ReceiptLine> in their order on the receipt
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * The version it was loaded with; 0 for a receipt that was never saved.
     */
    public function loadedVersion(): int
    {
        return $this->loadedVersion;
    }

    public function hasUnsavedChanges(): bool
    {
        return $this->version !== $this->loadedVersion;
    }

    private function changed(): void
    {
        ++$this->version;
    }
}
