<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Configuration\ConfigurationCheck;
use App\Shared\Infrastructure\Console\CheckConfigurationConsoleCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckConfigurationConsoleCommandTest extends TestCase
{
    #[Test]
    public function it_succeeds_when_every_check_passes(): void
    {
        $tester = $this->runCommand([self::checkWith(), self::checkWith()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The configuration is valid.', $tester->getDisplay());
    }

    #[Test]
    public function it_fails_and_prints_every_problem_of_every_check(): void
    {
        $tester = $this->runCommand([self::checkWith('A is missing.'), self::checkWith(), self::checkWith('B is invalid.', 'C is missing.')]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('A is missing.', $display);
        self::assertStringContainsString('B is invalid.', $display);
        self::assertStringContainsString('C is missing.', $display);
    }

    /**
     * @param list<ConfigurationCheck> $checks
     */
    private function runCommand(array $checks): CommandTester
    {
        $tester = new CommandTester(new Command(null, new CheckConfigurationConsoleCommand($checks)));
        $tester->execute([]);

        return $tester;
    }

    private static function checkWith(string ...$problems): ConfigurationCheck
    {
        return new readonly class(array_values($problems)) implements ConfigurationCheck {
            /**
             * @param list<string> $problems
             */
            public function __construct(private array $problems)
            {
            }

            public function problems(): array
            {
                return $this->problems;
            }
        };
    }
}
