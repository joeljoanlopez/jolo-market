<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Shared shopping lists (E4.12). Users and products belong to other contexts, so they are
 * referenced by id without foreign keys; a product the catalog no longer knows is shown unavailable.
 */
final class Version20261111120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the shopping_list, list_member and list_item tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE shopping_list (
                id BINARY(16) NOT NULL,
                owner_id BINARY(16) NOT NULL,
                share_token VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Secret of the join link (PD-2)',
                name VARCHAR(80) NOT NULL,
                version INT UNSIGNED NOT NULL COMMENT 'Moves on every change: ETag and optimistic lock (PD-43)',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX uniq_shopping_list_share_token (share_token),
                INDEX idx_shopping_list_owner (owner_id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE list_member (
                list_id BINARY(16) NOT NULL,
                user_id BINARY(16) NOT NULL,
                joined_at DATETIME(6) NOT NULL,
                PRIMARY KEY (list_id, user_id),
                INDEX idx_list_member_user (user_id),
                CONSTRAINT fk_list_member_list FOREIGN KEY (list_id) REFERENCES shopping_list (id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE list_item (
                id BINARY(16) NOT NULL,
                list_id BINARY(16) NOT NULL,
                type VARCHAR(16) NOT NULL COMMENT 'free_text or product',
                text VARCHAR(120) DEFAULT NULL,
                product_id BINARY(16) DEFAULT NULL,
                quantity TINYINT UNSIGNED NOT NULL,
                checked TINYINT(1) NOT NULL,
                position SMALLINT UNSIGNED NOT NULL COMMENT '0-based order in the list',
                PRIMARY KEY (id),
                INDEX idx_list_item_list_position (list_id, position),
                CONSTRAINT fk_list_item_list FOREIGN KEY (list_id) REFERENCES shopping_list (id) ON DELETE CASCADE
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE list_item');
        $this->addSql('DROP TABLE list_member');
        $this->addSql('DROP TABLE shopping_list');
    }
}
