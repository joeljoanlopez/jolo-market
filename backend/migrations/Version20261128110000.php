<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Queue of the Doctrine Messenger transport (E10.23): the receipts transport and its failed messages. It is
 * created here instead of by auto_setup, so the worker never alters the schema.
 */
final class Version20261128110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the messenger_messages table of the Doctrine Messenger transport.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id BIGINT AUTO_INCREMENT NOT NULL,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL,
                available_at DATETIME NOT NULL,
                delivered_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX idx_messenger_messages_queue (queue_name, available_at, delivered_at, id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
    }
}
