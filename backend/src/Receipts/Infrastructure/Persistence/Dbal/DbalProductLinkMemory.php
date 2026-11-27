<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Persistence\Dbal;

use App\Receipts\Domain\Port\ProductLinkMemory;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\Clock;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * receipt_product_link, keyed by supermarket and normalized text (PD-67): remembering a text again
 * replaces its product, so the last confirmation wins.
 */
final readonly class DbalProductLinkMemory implements ProductLinkMemory
{
    public function __construct(
        private Connection $connection,
        private Clock $clock,
    ) {
    }

    public function find(SupermarketId $supermarketId, LineText $text): ?ProductId
    {
        $productId = $this->connection->fetchOne(
            'SELECT product_id FROM receipt_product_link WHERE supermarket_id = ? AND normalized_text = ?',
            [$supermarketId->value(), $text->normalized()],
        );

        return \is_string($productId) ? ProductId::fromString(BinaryUuid::fromDatabase($productId)) : null;
    }

    public function remember(SupermarketId $supermarketId, LineText $text, ProductId $productId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO receipt_product_link (supermarket_id, normalized_text, product_id, remembered_at)
             VALUES (?, ?, ?, ?) AS new
             ON DUPLICATE KEY UPDATE product_id = new.product_id, remembered_at = new.remembered_at',
            [$supermarketId->value(), $text->normalized(), BinaryUuid::toDatabase($productId->value()), DbalDateTime::toDatabase($this->clock->now())],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING],
        );
    }
}
