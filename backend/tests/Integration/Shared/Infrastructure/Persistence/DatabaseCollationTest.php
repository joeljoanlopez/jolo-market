<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseCollationTest extends KernelTestCase
{
    private const string TABLE = 'collation_probe';

    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->dropProbeTable();
    }

    protected function tearDown(): void
    {
        $this->dropProbeTable();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_tables_with_utf8mb4_0900_ai_ci_by_default(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(50)->create())
            ->setOptions($schemaManager->createSchemaConfig()->getDefaultTableOptions())
            ->create();
        $schemaManager->createTable($table);

        $collation = $this->connection->fetchOne(
            'SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [self::TABLE],
        );

        self::assertSame('utf8mb4_0900_ai_ci', $collation);
    }

    #[Test]
    public function it_declares_utf8mb4_0900_ai_ci_as_the_default_table_collation(): void
    {
        $options = $this->connection->createSchemaManager()->createSchemaConfig()->getDefaultTableOptions();

        self::assertSame('utf8mb4', $options['charset'] ?? null);
        self::assertSame('utf8mb4_0900_ai_ci', $options['collation'] ?? null);
    }

    private function dropProbeTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);
    }
}
