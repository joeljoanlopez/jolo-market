<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * First-admin setup code (E3.22, PD-14). At most one row; only the hash is stored.
 */
final class Version20261109180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the setup_code table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE setup_code (
                id TINYINT UNSIGNED NOT NULL COMMENT 'Always 1: there is one code at most',
                code_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of the normalized code',
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE setup_code');
    }
}
