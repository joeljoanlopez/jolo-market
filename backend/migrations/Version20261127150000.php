<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Product search runs on Typesense (E16.8, PD-100), so nothing uses the FULLTEXT index any more.
 */
final class Version20261127150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the FULLTEXT index of product(name, brand).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP INDEX ft_product_name_brand');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD FULLTEXT INDEX ft_product_name_brand (name, brand)');
    }
}
