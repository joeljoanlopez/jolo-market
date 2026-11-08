<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalog tables (E1.11) and the three MVP supermarkets (PD-1).
 *
 * Datetimes are UTC. Quantities are thousandths of the unit, money is integer cents.
 */
final class Version20261108200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create supermarket, product, price_snapshot and ingestion_run; seed the MVP supermarkets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supermarket (
                id VARCHAR(32) NOT NULL,
                name VARCHAR(100) NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE product (
                id BINARY(16) NOT NULL,
                supermarket_id VARCHAR(32) NOT NULL,
                external_id VARCHAR(191) NOT NULL,
                name VARCHAR(255) NOT NULL,
                brand VARCHAR(255) DEFAULT NULL,
                category VARCHAR(512) DEFAULT NULL,
                image_url VARCHAR(2048) DEFAULT NULL,
                package_quantity INT UNSIGNED DEFAULT NULL COMMENT 'Thousandths of package_unit',
                package_unit VARCHAR(8) DEFAULT NULL,
                price_cents INT UNSIGNED NOT NULL,
                unit_price_cents INT UNSIGNED DEFAULT NULL,
                unit_price_unit VARCHAR(8) DEFAULT NULL,
                available TINYINT(1) NOT NULL,
                last_seen_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX uniq_product_supermarket_external_id (supermarket_id, external_id),
                INDEX idx_product_supermarket_available_seen (supermarket_id, available, last_seen_at),
                FULLTEXT INDEX ft_product_name_brand (name, brand),
                CONSTRAINT fk_product_supermarket FOREIGN KEY (supermarket_id) REFERENCES supermarket (id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE price_snapshot (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                product_id BINARY(16) NOT NULL,
                price_cents INT UNSIGNED NOT NULL,
                unit_price_cents INT UNSIGNED DEFAULT NULL,
                unit_price_unit VARCHAR(8) DEFAULT NULL,
                captured_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_price_snapshot_product_captured (product_id, captured_at),
                CONSTRAINT fk_price_snapshot_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE ingestion_run (
                id BINARY(16) NOT NULL,
                supermarket_id VARCHAR(32) NOT NULL,
                started_at DATETIME(6) NOT NULL,
                finished_at DATETIME(6) DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                products_seen INT UNSIGNED NOT NULL,
                products_skipped INT UNSIGNED NOT NULL,
                products_marked_unavailable INT UNSIGNED NOT NULL,
                error TEXT DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX idx_ingestion_run_supermarket_started (supermarket_id, started_at),
                CONSTRAINT fk_ingestion_run_supermarket FOREIGN KEY (supermarket_id) REFERENCES supermarket (id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO supermarket (id, name) VALUES
                ('mercadona', 'Mercadona'),
                ('bonpreu', 'Bonpreu i Esclat'),
                ('aldi', 'Aldi')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE price_snapshot');
        $this->addSql('DROP TABLE ingestion_run');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE supermarket');
    }
}
