<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\ReceiptLineId;
use App\Shared\Domain\ValueObject\Money;

/**
 * One line of a receipt (PD-66). A product line has a quantity and may be linked to a product of
 * the receipt's supermarket, or carry a suggested one that is not linked until accepted (PD-67).
 * Only the Receipt changes it.
 */
final class ReceiptLine
{
    private function __construct(
        private readonly ReceiptLineId $id,
        private readonly LineKind $kind,
        private LineText $text,
        private ?LineQuantity $quantity,
        private Money $amount,
        private ?ProductId $linkedProductId,
        private ?ProductId $suggestedProductId,
    ) {
        self::assertPositive($amount);
    }

    public static function product(ReceiptLineId $id, LineText $text, LineQuantity $quantity, Money $amount): self
    {
        return new self($id, LineKind::PRODUCT, $text, $quantity, $amount, null, null);
    }

    public static function discount(ReceiptLineId $id, LineText $text, Money $amount): self
    {
        return new self($id, LineKind::DISCOUNT, $text, null, $amount, null, null);
    }

    /**
     * Rebuilds a stored line. Only persistence adapters call it.
     */
    public static function reconstitute(
        ReceiptLineId $id,
        LineKind $kind,
        LineText $text,
        ?LineQuantity $quantity,
        Money $amount,
        ?ProductId $linkedProductId,
        ?ProductId $suggestedProductId,
    ): self {
        return new self($id, $kind, $text, $quantity, $amount, $linkedProductId, $suggestedProductId);
    }

    public function id(): ReceiptLineId
    {
        return $this->id;
    }

    public function kind(): LineKind
    {
        return $this->kind;
    }

    public function isProduct(): bool
    {
        return LineKind::PRODUCT === $this->kind;
    }

    public function text(): LineText
    {
        return $this->text;
    }

    /**
     * Null for a discount line.
     */
    public function quantity(): ?LineQuantity
    {
        return $this->quantity;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function linkedProductId(): ?ProductId
    {
        return $this->linkedProductId;
    }

    public function suggestedProductId(): ?ProductId
    {
        return $this->suggestedProductId;
    }

    private static function assertPositive(Money $amount): void
    {
        if (0 === $amount->cents()) {
            throw InvalidReceiptAmount::notPositive();
        }
    }
}
