<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Receipts (E10): a receipt of a list, its lines and the shared "text → product" memory (PD-63 to PD-67).
 * The list, the uploader and the products belong to other contexts, so there are no foreign keys to them:
 * deleting a list removes its receipts through the ShoppingListDeleted event (PD-63).
 */
final class Version20261128100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the receipt, receipt_line and receipt_product_link tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE receipt (
                id BINARY(16) NOT NULL,
                list_id BINARY(16) NOT NULL,
                supermarket_id VARCHAR(32) NOT NULL,
                uploader_id BINARY(16) NOT NULL,
                photo_type VARCHAR(8) NOT NULL,
                uploaded_at DATETIME(6) NOT NULL,
                status VARCHAR(16) NOT NULL,
                read_by VARCHAR(8) DEFAULT NULL COMMENT 'ai, ocr or none; null while reading (PD-114)',
                receipt_date DATE DEFAULT NULL COMMENT 'A day in Europe/Madrid (PD-66)',
                total_cents INT UNSIGNED DEFAULT NULL,
                version INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_receipt_list (list_id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE receipt_line (
                id BINARY(16) NOT NULL,
                receipt_id BINARY(16) NOT NULL,
                position SMALLINT UNSIGNED NOT NULL,
                kind VARCHAR(16) NOT NULL,
                text VARCHAR(120) NOT NULL,
                quantity_unit VARCHAR(8) DEFAULT NULL,
                quantity INT UNSIGNED DEFAULT NULL COMMENT 'Units, or thousandths of a kg',
                amount_cents INT UNSIGNED NOT NULL,
                linked_product_id BINARY(16) DEFAULT NULL,
                suggested_product_id BINARY(16) DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX idx_receipt_line_receipt_position (receipt_id, position),
                CONSTRAINT fk_receipt_line_receipt FOREIGN KEY (receipt_id) REFERENCES receipt (id) ON DELETE CASCADE
            )
            SQL);

        // The text is normalized by the application (case and spaces, PD-67); a binary collation compares the rest as is.
        $this->addSql(<<<'SQL'
            CREATE TABLE receipt_product_link (
                supermarket_id VARCHAR(32) NOT NULL,
                normalized_text VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                product_id BINARY(16) NOT NULL,
                remembered_at DATETIME(6) NOT NULL,
                PRIMARY KEY (supermarket_id, normalized_text)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE receipt_product_link');
        $this->addSql('DROP TABLE receipt_line');
        $this->addSql('DROP TABLE receipt');
    }
}
