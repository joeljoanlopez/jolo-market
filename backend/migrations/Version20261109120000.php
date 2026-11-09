<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Identity users (E3.14). Only hashes of passwords and activation tokens are stored, and TOTP
 * secrets are encrypted. Datetimes are UTC.
 */
final class Version20261109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the user table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user (
                id BINARY(16) NOT NULL,
                username VARCHAR(32) NOT NULL COMMENT 'Lowercase (PD-39)',
                role VARCHAR(16) NOT NULL,
                status VARCHAR(32) NOT NULL,
                password_hash VARCHAR(255) DEFAULT NULL,
                totp_secret_encrypted VARCHAR(255) DEFAULT NULL,
                totp_confirmed_at DATETIME(6) DEFAULT NULL,
                pending_totp_secret_encrypted VARCHAR(255) DEFAULT NULL COMMENT 'Enrolled, not confirmed yet',
                activation_token_hash CHAR(64) DEFAULT NULL COMMENT 'SHA-256 of the plain token',
                activation_expires_at DATETIME(6) DEFAULT NULL,
                session_version INT UNSIGNED NOT NULL COMMENT 'Sessions started with another version are ended',
                created_at DATETIME(6) NOT NULL,
                created_by BINARY(16) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX uniq_user_username (username),
                UNIQUE INDEX uniq_user_activation_token_hash (activation_token_hash),
                CONSTRAINT fk_user_created_by FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user');
    }
}
