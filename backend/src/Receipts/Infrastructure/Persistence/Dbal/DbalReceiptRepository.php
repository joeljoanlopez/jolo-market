<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Persistence\Dbal;

use App\Receipts\Domain\Model\LineKind;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Model\ReceiptLine;
use App\Receipts\Domain\Port\ReceiptChangedConcurrently;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\QuantityUnit;
use App\Receipts\Domain\ValueObject\ReadBy;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\ReceiptLineId;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Stores the aggregate in receipt and receipt_line. Saving a loaded receipt updates its row only
 * while the stored version is still the loaded one, so an edit never brings back a receipt that
 * was deleted meanwhile (PD-129), then rewrites its lines in the same transaction.
 */
final readonly class DbalReceiptRepository implements ReceiptRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function save(Receipt $receipt): void
    {
        $this->connection->transactional(function (Connection $connection) use ($receipt): void {
            $id = BinaryUuid::toDatabase($receipt->id()->value());
            $mutable = [
                'status' => $receipt->status()->value,
                'read_by' => $receipt->readBy()?->value,
                'receipt_date' => $receipt->date()?->value(),
                'total_cents' => $receipt->total()?->cents(),
                'version' => $receipt->version(),
            ];

            if (0 === $receipt->loadedVersion()) {
                $connection->insert(
                    'receipt',
                    [
                        'id' => $id,
                        'list_id' => BinaryUuid::toDatabase($receipt->listId()->value()),
                        'supermarket_id' => $receipt->supermarketId()->value(),
                        'uploader_id' => BinaryUuid::toDatabase($receipt->uploaderId()->value()),
                        'photo_type' => $receipt->photoType()->value,
                        'uploaded_at' => DbalDateTime::toDatabase($receipt->uploadedAt()),
                        ...$mutable,
                    ],
                    ['id' => ParameterType::BINARY, 'list_id' => ParameterType::BINARY, 'uploader_id' => ParameterType::BINARY, 'version' => ParameterType::INTEGER],
                );
            } else {
                $updated = $connection->update(
                    'receipt',
                    $mutable,
                    ['id' => $id, 'version' => $receipt->loadedVersion()],
                    ['id' => ParameterType::BINARY, 'version' => ParameterType::INTEGER],
                );
                if (1 !== (int) $updated) {
                    throw ReceiptChangedConcurrently::withId($receipt->id()->value());
                }
                $connection->delete('receipt_line', ['receipt_id' => $id], ['receipt_id' => ParameterType::BINARY]);
            }

            foreach ($receipt->lines() as $position => $line) {
                $this->insertLine($connection, $id, $position, $line);
            }
        });
    }

    public function remove(ReceiptId $id): void
    {
        // Its lines go with it (ON DELETE CASCADE).
        $this->connection->delete('receipt', ['id' => BinaryUuid::toDatabase($id->value())], ['id' => ParameterType::BINARY]);
    }

    public function findById(ReceiptId $id): ?Receipt
    {
        return $this->findWhere('id = ?', BinaryUuid::toDatabase($id->value()))[0] ?? null;
    }

    public function findByList(ListId $listId): array
    {
        return $this->findWhere('list_id = ?', BinaryUuid::toDatabase($listId->value()));
    }

    public function removeByList(ListId $listId): void
    {
        $this->connection->delete('receipt', ['list_id' => BinaryUuid::toDatabase($listId->value())], ['list_id' => ParameterType::BINARY]);
    }

    private function insertLine(Connection $connection, string $receiptId, int $position, ReceiptLine $line): void
    {
        $linked = $line->linkedProductId();
        $suggested = $line->suggestedProductId();
        $connection->insert(
            'receipt_line',
            [
                'id' => BinaryUuid::toDatabase($line->id()->value()),
                'receipt_id' => $receiptId,
                'position' => $position,
                'kind' => $line->kind()->value,
                'text' => $line->text()->value(),
                'quantity_unit' => $line->quantity()?->unit()->value,
                'quantity' => $line->quantity()?->value(),
                'amount_cents' => $line->amount()->cents(),
                'linked_product_id' => null === $linked ? null : BinaryUuid::toDatabase($linked->value()),
                'suggested_product_id' => null === $suggested ? null : BinaryUuid::toDatabase($suggested->value()),
            ],
            [
                'id' => ParameterType::BINARY,
                'receipt_id' => ParameterType::BINARY,
                'position' => ParameterType::INTEGER,
                'linked_product_id' => ParameterType::BINARY,
                'suggested_product_id' => ParameterType::BINARY,
            ],
        );
    }

    /**
     * @return list<Receipt>
     */
    private function findWhere(string $condition, string $binaryValue): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, list_id, supermarket_id, uploader_id, photo_type, uploaded_at, status, read_by, receipt_date, total_cents, version
             FROM receipt WHERE '.$condition,
            [$binaryValue],
            [ParameterType::BINARY],
        );

        return array_map(fn (array $row): Receipt => $this->receiptFrom($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function receiptFrom(array $row): Receipt
    {
        $id = DbalValue::string($row['id']);
        $readBy = DbalValue::nullableString($row['read_by']);
        $date = DbalValue::nullableString($row['receipt_date']);
        $total = DbalValue::nullableInt($row['total_cents']);

        return Receipt::reconstitute(
            ReceiptId::fromString(BinaryUuid::fromDatabase($id)),
            ListId::fromString(BinaryUuid::fromDatabase(DbalValue::string($row['list_id']))),
            SupermarketId::fromString(DbalValue::string($row['supermarket_id'])),
            UserId::fromString(BinaryUuid::fromDatabase(DbalValue::string($row['uploader_id']))),
            PhotoType::from(DbalValue::string($row['photo_type'])),
            DbalDateTime::fromDatabase(DbalValue::string($row['uploaded_at'])),
            ReceiptStatus::from(DbalValue::string($row['status'])),
            null === $readBy ? null : ReadBy::from($readBy),
            null === $date ? null : ReceiptDate::fromString($date),
            null === $total ? null : Money::eur($total),
            $this->linesOf($id),
            DbalValue::int($row['version']),
        );
    }

    /**
     * @return list<ReceiptLine>
     */
    private function linesOf(string $receiptId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, kind, text, quantity_unit, quantity, amount_cents, linked_product_id, suggested_product_id
             FROM receipt_line WHERE receipt_id = ? ORDER BY position',
            [$receiptId],
            [ParameterType::BINARY],
        );

        return array_map(static function (array $row): ReceiptLine {
            $unit = DbalValue::nullableString($row['quantity_unit']);
            $quantity = DbalValue::nullableInt($row['quantity']);
            $linked = DbalValue::nullableString($row['linked_product_id']);
            $suggested = DbalValue::nullableString($row['suggested_product_id']);

            return ReceiptLine::reconstitute(
                ReceiptLineId::fromString(BinaryUuid::fromDatabase(DbalValue::string($row['id']))),
                LineKind::from(DbalValue::string($row['kind'])),
                LineText::fromString(DbalValue::string($row['text'])),
                null === $unit || null === $quantity ? null : LineQuantity::of(QuantityUnit::from($unit), $quantity),
                Money::eur(DbalValue::int($row['amount_cents'])),
                null === $linked ? null : ProductId::fromString(BinaryUuid::fromDatabase($linked)),
                null === $suggested ? null : ProductId::fromString(BinaryUuid::fromDatabase($suggested)),
            );
        }, $rows);
    }
}
