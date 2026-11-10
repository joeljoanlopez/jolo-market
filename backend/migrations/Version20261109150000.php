<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * TOTP replay protection: the last 30-second time step a user's code was accepted for.
 */
final class Version20261109150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.totp_last_used_step.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD totp_last_used_step BIGINT UNSIGNED DEFAULT NULL COMMENT 'Unix time / 30 of the last accepted code' AFTER session_version");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP totp_last_used_step');
    }
}
