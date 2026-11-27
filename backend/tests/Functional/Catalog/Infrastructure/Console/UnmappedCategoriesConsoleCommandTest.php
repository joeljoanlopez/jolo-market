<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Console;

use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UnmappedCategoriesConsoleCommandTest extends KernelTestCase
{
    private Connection $connection;
    private int $products = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM price_snapshot');
        $this->connection->executeStatement('DELETE FROM product');
    }

    #[Test]
    public function it_lists_per_supermarket_the_categories_in_other_with_the_most_products_first(): void
    {
        $this->products('mercadona', 'Lácteos y huevos > Leche', 4);
        $this->products('mercadona', 'Juguetes > Puzles', 1);
        $this->products('mercadona', 'Bazar', 3, available: false);
        $this->products('aldi', 'Ofertas', 2);
        $this->products('aldi', null, 5);
        $before = $this->rowCount();

        $command = $this->command();
        $exitCode = $command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = preg_replace('/\s+/', ' ', $command->getDisplay()) ?? '';
        self::assertMatchesRegularExpression('/aldi .*2 .*Ofertas .*mercadona .*3 .*Bazar .*1 .*Juguetes > Puzles/', $display);
        self::assertStringNotContainsString('Leche', $display);
        self::assertSame($before, $this->rowCount());
    }

    #[Test]
    public function it_says_so_when_every_category_has_a_section(): void
    {
        $this->products('bonpreu', 'Despensa > Aceites', 1);

        $command = $this->command();
        $exitCode = $command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Every stored category maps to a section', $command->getDisplay());
    }

    private function command(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel ?? throw new \LogicException())->find('app:catalog:unmapped-categories'));
    }

    private function products(string $supermarket, ?string $category, int $count, bool $available = true): void
    {
        for ($i = 0; $i < $count; ++$i) {
            ++$this->products;
            $this->connection->insert('product', [
                'id' => BinaryUuid::toDatabase(\sprintf('00000000-0000-4000-8000-%012d', $this->products)), 'supermarket_id' => $supermarket,
                'external_id' => (string) $this->products, 'name' => 'Producto', 'category' => $category, 'price_cents' => 100,
                'available' => $available, 'last_seen_at' => '2026-11-08 06:00:00',
            ], ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN]);
        }
    }

    private function rowCount(): mixed
    {
        return $this->connection->fetchOne('SELECT COUNT(*) FROM product');
    }
}
