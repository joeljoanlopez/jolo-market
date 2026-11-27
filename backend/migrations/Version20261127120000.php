<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The section a member chose for a list item (E14.3, PD-92). Existing rows stay NULL: free text
 * shows in "other" and products in their catalog section, so nothing is migrated (PD-94).
 */
final class Version20261127120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the nullable section column to list_item.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE list_item ADD section VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT 'Section chosen by hand (PD-92); NULL follows the catalog'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE list_item DROP section');
    }
}
